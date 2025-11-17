<?php
/**
 * tools/verify-phpmailer.php
 * Verify PHPMailer installation
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔍 PHPMailer Verification\n";
echo str_repeat("=", 50) . "\n\n";

// Test 1: Check vendor directory
echo "1️⃣ Checking vendor directory...\n";
$vendorPath = __DIR__ . '/../vendor';
if (is_dir($vendorPath)) {
    echo "   ✅ Vendor directory exists: $vendorPath\n";
} else {
    echo "   ❌ Vendor directory missing\n";
    exit(1);
}

// Test 2: Check autoload.php
echo "\n2️⃣ Checking autoload.php...\n";
$autoloadPath = $vendorPath . '/autoload.php';
if (file_exists($autoloadPath)) {
    echo "   ✅ Autoload file exists\n";
    require_once $autoloadPath;
    echo "   ✅ Autoload file loaded successfully\n";
} else {
    echo "   ❌ Autoload file missing at: $autoloadPath\n";
    echo "   Run: composer install\n";
    exit(1);
}

// Test 3: Check PHPMailer directory
echo "\n3️⃣ Checking PHPMailer installation...\n";
$phpmailerDir = $vendorPath . '/phpmailer/phpmailer';
if (is_dir($phpmailerDir)) {
    echo "   ✅ PHPMailer directory exists\n";
    
    $phpmailerFile = $phpmailerDir . '/src/PHPMailer.php';
    if (file_exists($phpmailerFile)) {
        echo "   ✅ PHPMailer.php file exists\n";
    } else {
        echo "   ❌ PHPMailer.php file missing\n";
    }
} else {
    echo "   ❌ PHPMailer directory missing\n";
    echo "   Run: composer require phpmailer/phpmailer\n";
    exit(1);
}

// Test 4: Try to load PHPMailer class
echo "\n4️⃣ Testing PHPMailer class loading...\n";
try {
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "   ✅ PHPMailer class loaded via autoloader\n";
    } else {
        echo "   ❌ PHPMailer class not found\n";
        
        // Try manual require
        echo "   Attempting manual require...\n";
        require_once $phpmailerDir . '/src/PHPMailer.php';
        require_once $phpmailerDir . '/src/SMTP.php';
        require_once $phpmailerDir . '/src/Exception.php';
        
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            echo "   ✅ PHPMailer loaded manually\n";
        } else {
            echo "   ❌ Still cannot load PHPMailer\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 5: Create PHPMailer instance
echo "\n5️⃣ Creating PHPMailer instance...\n";
try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    echo "   ✅ PHPMailer instance created successfully\n";
    echo "   Version: " . PHPMailer\PHPMailer\PHPMailer::VERSION . "\n";
} catch (Exception $e) {
    echo "   ❌ Failed to create instance: " . $e->getMessage() . "\n";
}

// Test 6: Check composer.json
echo "\n6️⃣ Checking composer.json...\n";
$composerJson = __DIR__ . '/../composer.json';
if (file_exists($composerJson)) {
    $composer = json_decode(file_get_contents($composerJson), true);
    
    if (isset($composer['require']['phpmailer/phpmailer'])) {
        echo "   ✅ phpmailer/phpmailer in require: " . $composer['require']['phpmailer/phpmailer'] . "\n";
    } else {
        echo "   ⚠️  phpmailer/phpmailer not in composer.json require section\n";
        echo "   Run: composer require phpmailer/phpmailer\n";
    }
    
    if (isset($composer['autoload'])) {
        echo "   ✅ Autoload section exists\n";
    }
} else {
    echo "   ❌ composer.json not found\n";
}

// Test 7: Check composer.lock
echo "\n7️⃣ Checking composer.lock...\n";
$composerLock = __DIR__ . '/../composer.lock';
if (file_exists($composerLock)) {
    $lock = json_decode(file_get_contents($composerLock), true);
    
    $found = false;
    foreach ($lock['packages'] ?? [] as $package) {
        if ($package['name'] === 'phpmailer/phpmailer') {
            $found = true;
            echo "   ✅ PHPMailer in composer.lock: version " . $package['version'] . "\n";
            break;
        }
    }
    
    if (!$found) {
        echo "   ⚠️  PHPMailer not found in composer.lock\n";
        echo "   This means it wasn't installed. Run: composer require phpmailer/phpmailer\n";
    }
} else {
    echo "   ⚠️  composer.lock not found - run composer install\n";
}

echo "\n" . str_repeat("=", 50) . "\n";

// Summary
$allGood = is_dir($phpmailerDir) && 
           file_exists($autoloadPath) && 
           class_exists('PHPMailer\PHPMailer\PHPMailer');

if ($allGood) {
    echo "✅ PHPMailer is properly installed and ready to use!\n";
} else {
    echo "❌ PHPMailer is NOT properly installed\n\n";
    echo "Fix steps:\n";
    echo "1. Run: composer require phpmailer/phpmailer\n";
    echo "2. Or: composer update\n";
    echo "3. Then test again: php tools/verify-phpmailer.php\n";
}