<?php
if (php_sapi_name() !== 'cli') {
    die("Run from command line only!\n");
}

$projectRoot = dirname(__DIR__);
$flagFile = $projectRoot . '/.db_setup_complete';

echo "\n=== RESET DATABASE SETUP ===\n\n";
echo "Flag file: $flagFile\n\n";

if (file_exists($flagFile)) {
    $setupTime = file_get_contents($flagFile);
    echo "Database was setup at: $setupTime\n";
    echo "\n⚠️  WARNING: This will allow database re-setup!\n";
    echo "Continue? (yes/no): ";
    
    $handle = fopen("php://stdin", "r");
    $confirmation = strtolower(trim(fgets($handle)));
    fclose($handle);
    
    if ($confirmation === 'yes') {
        if (unlink($flagFile)) {
            echo "\n✅ Flag file deleted!\n";
            echo "Run the app again to re-setup databases.\n\n";
        } else {
            echo "\n❌ Failed to delete flag file!\n\n";
        }
    } else {
        echo "\nCancelled.\n\n";
    }
} else {
    echo "✅ Flag file doesn't exist. Database will be setup on next run.\n\n";
}