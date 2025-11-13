<?php
/**
 * ============================================
 * tools/create_demo_users.php
 * Script để tạo demo accounts với password đã hash
 * ============================================
 * 
 * CÁCH CHẠY:
 * php tools/create_demo_users.php
 */

require_once __DIR__ . '/../shared/classes/DatabaseManager.php';

// Load .env
function loadEnv() {
    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) {
        die("❌ .env file không tồn tại!\n");
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

loadEnv();

echo "\n╔══════════════════════════════════════════════╗\n";
echo "║   Demo User Creator với Password Hash       ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

// Demo accounts configuration
$demoAccounts = [
    [
        'username' => 'admin',
        'password' => 'admin123',
        'name' => 'Administrator',
        'email' => 'admin@transportation.com',
        'phone' => '0901234567',
        'birthdate' => '1990-01-01',
        'status' => 'Active'
    ],
    [
        'username' => 'user',
        'password' => 'user123',
        'name' => 'Nguyễn Văn A',
        'email' => 'user@example.com',
        'phone' => '0912345678',
        'birthdate' => '1995-05-15',
        'status' => 'Active'
    ],
    [
        'username' => 'customer1',
        'password' => 'customer123',
        'name' => 'Trần Thị B',
        'email' => 'customer1@example.com',
        'phone' => '0923456789',
        'birthdate' => '1992-08-20',
        'status' => 'Active'
    ],
    [
        'username' => 'pending_user',
        'password' => 'pending123',
        'name' => 'Lê Văn C',
        'email' => 'pending@example.com',
        'phone' => '0934567890',
        'birthdate' => '1998-12-10',
        'status' => 'Pending'
    ],
];

try {
    $conn = DatabaseManager::getConnection('customer');
    
    echo "📝 Tạo demo accounts...\n\n";
    
    // Clear existing demo users (optional)
    $clearExisting = readline("⚠️  Xóa tất cả users hiện tại? (y/n): ");
    if (strtolower(trim($clearExisting)) === 'y') {
        $conn->exec("TRUNCATE TABLE RentalHistory");
        $conn->exec("TRUNCATE TABLE PaymentMethod");
        $conn->exec("TRUNCATE TABLE KYC");
        $conn->exec("TRUNCATE TABLE Users");
        echo "✅ Đã xóa dữ liệu cũ\n\n";
    }
    
    $stmt = $conn->prepare("
        INSERT INTO Users (username, password, name, email, phone, birthdate, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            password = VALUES(password),
            name = VALUES(name),
            email = VALUES(email),
            phone = VALUES(phone),
            birthdate = VALUES(birthdate),
            status = VALUES(status)
    ");
    
    echo "┌─────────────────────────────────────────────────────────────┐\n";
    echo "│ USERNAME      │ PASSWORD    │ PASSWORD HASH                │\n";
    echo "├─────────────────────────────────────────────────────────────┤\n";
    
    foreach ($demoAccounts as $account) {
        // Hash password
        $passwordHash = password_hash($account['password'], PASSWORD_DEFAULT);
        
        // Insert vào database
        $stmt->execute([
            $account['username'],
            $passwordHash,
            $account['name'],
            $account['email'],
            $account['phone'],
            $account['birthdate'],
            $account['status']
        ]);
        
        // Display info
        $shortHash = substr($passwordHash, 0, 30) . '...';
        printf("│ %-13s │ %-11s │ %-28s │\n", 
            $account['username'], 
            $account['password'],
            $shortHash
        );
    }
    
    echo "└─────────────────────────────────────────────────────────────┘\n\n";
    
    // Insert KYC data
    echo "📋 Tạo KYC records...\n";
    $conn->exec("
        INSERT INTO KYC (user_id, identity_number, verification_status, verified_at) 
        SELECT user_id, CONCAT('00123456789', user_id), 
               CASE WHEN status = 'Active' THEN 'Verified' ELSE 'Pending' END,
               CASE WHEN status = 'Active' THEN NOW() ELSE NULL END
        FROM Users
        WHERE user_id NOT IN (SELECT user_id FROM KYC)
    ");
    echo "✅ KYC records created\n\n";
    
    // Insert payment methods
    echo "💳 Tạo payment methods...\n";
    $conn->exec("
        INSERT IGNORE INTO PaymentMethod (user_id, type, provider, account_number, is_default)
        SELECT user_id, 
               CASE 
                   WHEN user_id % 3 = 0 THEN 'CreditCard'
                   WHEN user_id % 3 = 1 THEN 'EWallet'
                   ELSE 'BankTransfer'
               END,
               CASE 
                   WHEN user_id % 3 = 0 THEN 'Visa'
                   WHEN user_id % 3 = 1 THEN 'MoMo'
                   ELSE 'Vietcombank'
               END,
               CONCAT('****', LPAD(user_id, 4, '0')),
               TRUE
        FROM Users
        WHERE status = 'Active'
    ");
    echo "✅ Payment methods created\n\n";
    
    echo "╔══════════════════════════════════════════════╗\n";
    echo "║          ✅ SETUP HOÀN TẤT!                  ║\n";
    echo "╚══════════════════════════════════════════════╝\n\n";
    
    echo "🔑 Thông tin đăng nhập:\n\n";
    foreach ($demoAccounts as $account) {
        echo "   👤 {$account['username']} / {$account['password']}\n";
        echo "      📧 {$account['email']}\n";
        echo "      📊 Status: {$account['status']}\n\n";
    }
    
    echo "💡 Test login:\n";
    echo "   curl -X POST http://localhost/TransportationRenting/gateway/api/auth/login \\\n";
    echo "        -H 'Content-Type: application/json' \\\n";
    echo "        -d '{\"username\":\"admin\",\"password\":\"admin123\"}'\n\n";
    
} catch (Exception $e) {
    echo "❌ LỖI: " . $e->getMessage() . "\n";
    exit(1);
}