<?php
// ========================================
// quick-test.php - Kiểm tra nhanh hệ thống
// Chạy: php quick-test.php
// ========================================

echo "🧪 QUICK TEST - Transportation Renting System\n";
echo "=============================================\n\n";

$tests = [];
$passed = 0;
$failed = 0;

// Test 1: Check required files
echo "📁 Test 1: Kiểm tra files cần thiết...\n";
$requiredFiles = [
    'shared/classes/DatabaseManager.php',
    'shared/classes/ApiResponse.php',
    'shared/classes/ApiClient.php',
    'services/vehicle/public/index.php',
    'services/vehicle/public/health.php',
    'services/vehicle/classes/Vehicle.php',
    'services/vehicle/services/VehicleService.php',
    'frontend/api/cart-add.php',
    'frontend/api/cart-remove.php',
    'frontend/api/cart-count.php',
    'frontend/api/cart-clear.php',
    'frontend/api/cart-checkout.php'
];

foreach ($requiredFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "   ✅ $file\n";
        $passed++;
    } else {
        echo "   ❌ $file MISSING\n";
        $failed++;
    }
}

// Test 2: Check database connection
echo "\n🗄️  Test 2: Kiểm tra database connection...\n";
try {
    require_once __DIR__ . '/shared/classes/DatabaseManager.php';
    $db = DatabaseManager::getInstance('vehicle');
    echo "   ✅ Database connected\n";
    $passed++;
    
    // Test tables
    $tables = ['VehicleCatalog', 'VehicleUnits'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "   ✅ Table $table exists (" . $result['count'] . " records)\n";
            $passed++;
        } else {
            echo "   ❌ Table $table NOT FOUND\n";
            $failed++;
        }
    }
} catch (Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
    $failed++;
}

// Test 3: Check if services are running
echo "\n🌐 Test 3: Kiểm tra services...\n";
$services = [
    'Vehicle' => 'http://localhost:8002/health',
    'Customer' => 'http://localhost:8001/health',
    'Rental' => 'http://localhost:8003/health'
];

foreach ($services as $name => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        echo "   ✅ $name Service running (port " . parse_url($url, PHP_URL_PORT) . ")\n";
        $passed++;
    } else {
        echo "   ❌ $name Service NOT running (port " . parse_url($url, PHP_URL_PORT) . ")\n";
        echo "      Start with: php -S localhost:" . parse_url($url, PHP_URL_PORT) . " -t services/" . strtolower($name) . "/public\n";
        $failed++;
    }
}

// Test 4: Test Vehicle API endpoints
echo "\n🚗 Test 4: Kiểm tra Vehicle API endpoints...\n";
$endpoints = [
    '/available' => 'Get available vehicles',
    '/stats' => 'Get statistics',
    '/health' => 'Health check'
];

foreach ($endpoints as $endpoint => $description) {
    $url = 'http://localhost:8002' . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['success']) && $data['success']) {
            echo "   ✅ $endpoint - $description\n";
            $passed++;
        } else {
            echo "   ⚠️  $endpoint - Response but success=false\n";
            $failed++;
        }
    } else {
        echo "   ❌ $endpoint - Failed (HTTP $httpCode)\n";
        $failed++;
    }
}

// Test 5: Check sample data
echo "\n📊 Test 5: Kiểm tra dữ liệu mẫu...\n";
try {
    require_once __DIR__ . '/shared/classes/DatabaseManager.php';
    $db = DatabaseManager::getInstance('vehicle');
    
    // Count catalogs
    $stmt = $db->query("SELECT COUNT(*) as count FROM VehicleCatalog WHERE is_active = TRUE");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $catalogCount = (int)$result['count'];
    
    if ($catalogCount > 0) {
        echo "   ✅ VehicleCatalog có $catalogCount records\n";
        $passed++;
    } else {
        echo "   ❌ VehicleCatalog rỗng - Chạy: php run.php\n";
        $failed++;
    }
    
    // Count available units
    $stmt = $db->query("SELECT COUNT(*) as count FROM VehicleUnits WHERE status = 'Available'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $unitCount = (int)$result['count'];
    
    if ($unitCount > 0) {
        echo "   ✅ VehicleUnits có $unitCount xe available\n";
        $passed++;
    } else {
        echo "   ❌ Không có xe available - Chạy: php run.php\n";
        $failed++;
    }
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    $failed++;
}

// Summary
echo "\n" . str_repeat("=", 45) . "\n";
echo "📈 KẾT QUẢ:\n";
echo "   ✅ Passed: $passed\n";
echo "   ❌ Failed: $failed\n";
echo "   📊 Total: " . ($passed + $failed) . "\n";

if ($failed == 0) {
    echo "\n🎉 TẤT CẢ TESTS ĐỀU PASS! Hệ thống sẵn sàng.\n";
    echo "🌐 Truy cập: http://localhost/dashboard.php\n";
} else {
    echo "\n⚠️  CÓ $failed TESTS FAILED. Kiểm tra lại:\n";
    echo "   1. Chạy: php run.php (để setup database)\n";
    echo "   2. Start services theo hướng dẫn\n";
    echo "   3. Kiểm tra PHP error logs\n";
    echo "   4. Chạy: php quick-test.php lại\n";
}

echo "\n";