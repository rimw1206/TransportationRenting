<?php
/**
 * ============================================
 * start_service.php - FIXED WITH ENV LOADING
 * Usage: php start_service.php <service>
 * ============================================
 */

// Load .env FIRST before starting service
function loadEnvFile() {
    $envFile = __DIR__ . '/.env';
    
    if (!file_exists($envFile)) {
        echo "⚠️  .env file not found, service will use default config\n";
        return false;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes
            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
    
    return true;
}

// Load environment variables NOW
echo "📝 Loading environment variables...\n";
if (loadEnvFile()) {
    $jwtSecret = getenv('JWT_SECRET');
    if ($jwtSecret) {
        echo "   ✅ JWT_SECRET loaded: " . substr($jwtSecret, 0, 20) . "... (" . strlen($jwtSecret) . " chars)\n";
    }
} else {
    echo "   ⚠️  Using default configuration\n";
}
echo "\n";

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
        exec("netstat -ano | findstr :$port 2>nul", $output);
        return !empty($output);
    } else {
        $output = [];
        exec("lsof -i :$port 2>/dev/null", $output);
        return count($output) > 0;
    }
}

if (isPortRunning($port)) {
    echo "⚠️  Service '$serviceName' is already running on port $port\n";
    echo "🔄 Stopping existing service...\n";
    
    // Kill existing process on this port
    if (strtoupper(substr(PHP_OS,0,3))==='WIN') {
        exec("netstat -ano | findstr :$port", $output);
        foreach ($output as $line) {
            if (preg_match('/\s+(\d+)$/', $line, $matches)) {
                $pid = $matches[1];
                exec("taskkill /F /PID $pid 2>nul");
                echo "   Killed PID: $pid\n";
            }
        }
    } else {
        exec("lsof -ti :$port | xargs kill -9 2>/dev/null");
    }
    
    sleep(2);
    echo "   ✅ Old service stopped\n\n";
}

// Build environment string to pass to new process
$envString = '';
foreach ($_ENV as $key => $value) {
    // Only pass important vars to avoid command line length issues
    if (strpos($key, 'DB_') !== false || $key === 'JWT_SECRET' || strpos($key, 'REDIS_') !== false) {
        if (strtoupper(substr(PHP_OS,0,3))==='WIN') {
            $envString .= "set $key=" . escapeshellarg($value) . " && ";
        } else {
            $envString .= "$key=" . escapeshellarg($value) . " ";
        }
    }
}

// Start service WITH environment variables
echo "🚀 Starting service '$serviceName' on port $port...\n";

if (strtoupper(substr(PHP_OS,0,3))==='WIN') {
    // Windows: Pass environment via SET commands
    $cmd = "start \"$serviceName\" /MIN cmd /c \"$envString php -S localhost:$port -t \"$folder\"\"";
    pclose(popen($cmd,'r'));
} else {
    // Linux/Mac: Export environment variables
    $cmd = "$envString nohup php -S localhost:$port -t \"$folder\" > /dev/null 2>&1 &";
    exec($cmd);
}

sleep(2);

if (isPortRunning($port)) {
    echo "✅ Service '$serviceName' started on port $port\n";
    
    // Verify JWT_SECRET is accessible to service
    echo "\n🔍 Verifying service configuration...\n";
    
    // Create a test file to check environment
    $testFile = $folder . '/test-env-' . time() . '.php';
    file_put_contents($testFile, '<?php echo json_encode(["jwt_secret_length" => strlen(getenv("JWT_SECRET") ?: ""), "jwt_secret_hash" => hash("sha256", getenv("JWT_SECRET") ?: "")]); ?>');
    
    $ch = curl_init("http://localhost:$port/" . basename($testFile));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data) {
            $expectedHash = hash('sha256', getenv('JWT_SECRET'));
            if ($data['jwt_secret_hash'] === $expectedHash) {
                echo "   ✅ JWT_SECRET correctly loaded in service\n";
                echo "      Length: {$data['jwt_secret_length']} chars\n";
            } else {
                echo "   ❌ JWT_SECRET mismatch!\n";
                echo "      Expected hash: $expectedHash\n";
                echo "      Service hash:  {$data['jwt_secret_hash']}\n";
            }
        }
    }
    
    @unlink($testFile);
    
    echo "\n📍 Service URL: http://localhost:$port\n";
    echo "🧪 Test with: php quick-test-login.php\n\n";
    
} else {
    echo "❌ Failed to start service '$serviceName'\n";
}