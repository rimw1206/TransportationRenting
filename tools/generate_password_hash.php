<?php
if (php_sapi_name() !== 'cli') {
    die("Run from command line only!\n");
}

echo "\n=== PASSWORD HASH GENERATOR ===\n\n";

if ($argc > 1) {
    $password = $argv[1];
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "Password: $password\n";
    echo "Hash: $hash\n";
    echo "\nVerified: " . (password_verify($password, $hash) ? "✓" : "✗") . "\n\n";
    exit(0);
}

$passwords = [
    'admin123' => 'admin',
    'user123' => 'user',
    'customer123' => 'customer1',
    'pending123' => 'pending_user'
];

foreach ($passwords as $password => $username) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "[$username]\n";
    echo "Password: $password\n";
    echo "Hash: $hash\n";
    echo "SQL: UPDATE Users SET password = '$hash' WHERE username = '$username';\n\n";
}

echo "✅ Done!\n";
