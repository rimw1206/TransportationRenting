<?php
/**
 * ============================================
 * check-customer-errors.php
 * Kiểm tra syntax errors trong customer service
 * ============================================
 */

echo "🔍 Checking Customer Service for Errors\n";
echo "========================================\n\n";

$files = [
    'services/customer/public/index.php' => 'Entry point',
    'services/customer/api/auth/login.php' => 'Login endpoint',
    'services/customer/api/profile/get.php' => 'Profile GET',
    'services/customer/api/profile/update.php' => 'Profile UPDATE',
    'services/customer/api/profile/delete.php' => 'Profile DELETE',
    'services/customer/classes/Customer.php' => 'Customer model',
    'shared/classes/JWTHandler.php' => 'JWT Handler',
    'env-bootstrap.php' => 'Environment loader',
];

$hasErrors = false;

foreach ($files as $file => $description) {
    $path = __DIR__ . '/' . $file;
    
    echo "📄 Checking: $description\n";
    echo "   File: $file\n";
    
    if (!file_exists($path)) {
        echo "   ❌ FILE NOT FOUND!\n\n";
        $hasErrors = true;
        continue;
    }
    
    // Check syntax
    $output = [];
    $return = 0;
    exec("php -l \"$path\" 2>&1", $output, $return);
    
    if ($return !== 0) {
        echo "   ❌ SYNTAX ERROR:\n";
        foreach ($output as $line) {
            echo "      $line\n";
        }
        $hasErrors = true;
    } else {
        echo "   ✅ No syntax errors\n";
    }
    
    echo "\n";
}

if ($hasErrors) {
    echo "❌ ERRORS FOUND! Fix them before starting service.\n\n";
} else {
    echo "✅ All files look good!\n\n";
    
    // Try to start service in foreground to see runtime errors
    echo "🚀 Attempting to start service...\n";
    echo "   Press Ctrl+C to stop\n";
    echo "   " . str_repeat("=", 60) . "\n\n";
    
    $folder = __DIR__ . '/services/customer/public';
    passthru("php -S localhost:8001 -t \"$folder\"");
}