<?php
/**
 * tools/test-autoloader.php
 * Test if composer autoloader actually works
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔍 Testing Composer Autoloader\n";
echo str_repeat("=", 60) . "\n\n";

// Test 1: Find composer.json
echo "1️⃣ Finding project root...\n";
$currentDir = __DIR__;
$projectRoot = null;

for ($i = 0; $i < 5; $i++) {
    if (file_exists($currentDir . '/composer.json')) {
        $projectRoot = $currentDir;
        break;
    }
    $currentDir = dirname($currentDir);
}

if ($projectRoot) {
    echo "   ✅ Found at: $projectRoot\n\n";
} else {
    die("   ❌ Could not find composer.json\n");
}

// Test 2: Check vendor directory
echo "2️⃣ Checking vendor directory...\n";
$vendorDir = $projectRoot . '/vendor';
if (is_dir($vendorDir)) {
    echo "   ✅ Vendor directory exists\n";
    
    // List contents
    $contents = scandir($vendorDir);
    echo "   Contents: " . implode(', ', array_slice($contents, 2, 5)) . "\n\n";
} else {
    die("   ❌ Vendor directory not found\n");
}

// Test 3: Check autoload.php
echo "3️⃣ Checking autoload.php...\n";
$autoloadPath = $vendorDir . '/autoload.php';
if (file_exists($autoloadPath)) {
    echo "   ✅ autoload.php exists\n";
    echo "   Size: " . filesize($autoloadPath) . " bytes\n\n";
} else {
    die("   ❌ autoload.php not found\n");
}

// Test 4: Load autoloader
echo "4️⃣ Loading autoloader...\n";
try {
    require_once $autoloadPath;
    echo "   ✅ Autoloader loaded without errors\n\n";
} catch (Exception $e) {
    die("   ❌ Error loading: " . $e->getMessage() . "\n");
}

// Test 5: Check registered namespaces
echo "5️⃣ Checking Composer ClassLoader...\n";
$classLoaders = spl_autoload_functions();
echo "   Registered autoloaders: " . count($classLoaders) . "\n";

foreach ($classLoaders as $loader) {
    if (is_array($loader) && $loader[0] instanceof Composer\Autoload\ClassLoader) {
        echo "   ✅ Found Composer ClassLoader\n";
        
        $classLoader = $loader[0];
        
        // Get PSR-4 prefixes
        $prefixes = $classLoader->getPrefixesPsr4();
        echo "   Registered PSR-4 namespaces:\n";
        foreach ($prefixes as $namespace => $paths) {
            echo "      - $namespace => " . implode(', ', $paths) . "\n";
        }
        
        break;
    }
}
echo "\n";

// Test 6: Try to load PHPMailer
echo "6️⃣ Testing PHPMailer class loading...\n";
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "   ✅ PHPMailer class is available!\n";
    echo "   Version: " . PHPMailer\PHPMailer\PHPMailer::VERSION . "\n";
} else {
    echo "   ❌ PHPMailer class NOT available\n";
    
    // Check if files exist
    $phpmailerFile = $vendorDir . '/phpmailer/phpmailer/src/PHPMailer.php';
    if (file_exists($phpmailerFile)) {
        echo "   ⚠️  PHPMailer.php file EXISTS at: $phpmailerFile\n";
        echo "   But class is not autoloading properly\n";
        
        // Try manual require
        echo "\n   Trying manual require...\n";
        require_once $phpmailerFile;
        require_once dirname($phpmailerFile) . '/SMTP.php';
        require_once dirname($phpmailerFile) . '/Exception.php';
        
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            echo "   ✅ Manual require worked!\n";
            echo "   This means autoloader is not configured properly\n";
        }
    } else {
        echo "   ❌ PHPMailer files NOT found\n";
        echo "   Run: composer require phpmailer/phpmailer\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Test complete!\n";