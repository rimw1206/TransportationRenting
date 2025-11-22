<?php
/**
 * ================================================
 * seed-demo-transactions.php
 * ✅ Tạo demo transactions với rentals
 * ✅ Run: php seed-demo-transactions.php
 * ================================================
 */

require_once __DIR__ . '/shared/classes/DatabaseManager.php';

echo "🌱 Seeding demo transactions...\n\n";

try {
    // Connect to databases
    $paymentDb = DatabaseManager::getInstance('payment');
    $rentalDb = DatabaseManager::getInstance('rental');
    
    // Check if transactions already exist
    $checkStmt = $paymentDb->query("SELECT COUNT(*) as count FROM Transactions");
    $existingCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($existingCount > 0) {
        echo "⚠️  Found {$existingCount} existing transactions.\n";
        echo "Do you want to clear and reseed? (yes/no): ";
        $answer = trim(fgets(STDIN));
        
        if (strtolower($answer) !== 'yes') {
            echo "❌ Cancelled.\n";
            exit;
        }
        
        // Clear existing data
        echo "🗑️  Clearing existing transactions...\n";
        $paymentDb->exec("SET FOREIGN_KEY_CHECKS = 0");
        $paymentDb->exec("TRUNCATE TABLE RentalPayments");
        $paymentDb->exec("TRUNCATE TABLE Invoice");
        $paymentDb->exec("TRUNCATE TABLE Transactions");
        $paymentDb->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo "   ✅ Cleared\n\n";
    }
    
    // Get existing rentals
    $rentalsStmt = $rentalDb->query("
        SELECT rental_id, user_id, vehicle_id, total_cost, status, start_time, end_time
        FROM Rentals 
        WHERE status IN ('Pending', 'Ongoing')
        ORDER BY rental_id
        LIMIT 10
    ");
    
    $rentals = $rentalsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($rentals)) {
        echo "❌ No rentals found in database!\n";
        echo "Please create some rentals first by:\n";
        echo "1. Login as user (user/user123)\n";
        echo "2. Go to vehicles page\n";
        echo "3. Rent some vehicles\n";
        exit;
    }
    
    echo "📦 Found " . count($rentals) . " rentals to create transactions for\n\n";
    
    $transactionCount = 0;
    $rentalPaymentCount = 0;
    
    // Create transactions
    $paymentDb->beginTransaction();
    
    // Transaction 1: Single rental (COD)
    if (isset($rentals[0])) {
        $rental = $rentals[0];
        
        echo "Creating Transaction 1 (COD - Single Rental)...\n";
        
        $txnCode = 'TXN-' . date('Ymd') . '-' . str_pad(1, 6, '0', STR_PAD_LEFT);
        $metadata = json_encode([
            'rental_ids' => [$rental['rental_id']],
            'rental_count' => 1,
            'cart_checkout' => false,
            'original_amount' => $rental['total_cost'],
            'discount_amount' => 0
        ]);
        
        $stmt = $paymentDb->prepare("
            INSERT INTO Transactions (
                user_id, amount, payment_method, payment_gateway,
                transaction_code, metadata, status, transaction_date
            ) VALUES (?, ?, 'COD', NULL, ?, ?, 'Pending', NOW())
        ");
        
        $stmt->execute([
            $rental['user_id'],
            $rental['total_cost'],
            $txnCode,
            $metadata
        ]);
        
        $txnId = $paymentDb->lastInsertId();
        
        // Link rental to transaction
        $linkStmt = $paymentDb->prepare("
            INSERT INTO RentalPayments (rental_id, transaction_id, amount)
            VALUES (?, ?, ?)
        ");
        $linkStmt->execute([$rental['rental_id'], $txnId, $rental['total_cost']]);
        
        $transactionCount++;
        $rentalPaymentCount++;
        
        echo "   ✅ Transaction #{$txnId}: {$txnCode}\n";
        echo "      Rental: #{$rental['rental_id']}, Amount: " . number_format($rental['total_cost']) . " VND\n\n";
    }
    
    // Transaction 2: Cart checkout with 2 rentals (VNPayQR)
    if (isset($rentals[1]) && isset($rentals[2])) {
        $rental1 = $rentals[1];
        $rental2 = $rentals[2];
        
        // Must be same user
        if ($rental1['user_id'] === $rental2['user_id']) {
            echo "Creating Transaction 2 (VNPayQR - Cart Checkout)...\n";
            
            $totalAmount = $rental1['total_cost'] + $rental2['total_cost'];
            $discountAmount = $totalAmount * 0.1; // 10% discount
            $finalAmount = $totalAmount - $discountAmount;
            
            $txnCode = 'TXN-' . date('Ymd') . '-' . str_pad(2, 6, '0', STR_PAD_LEFT);
            $metadata = json_encode([
                'rental_ids' => [$rental1['rental_id'], $rental2['rental_id']],
                'rental_count' => 2,
                'cart_checkout' => true,
                'original_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'promo_code' => 'NEW10'
            ]);
            
            $stmt = $paymentDb->prepare("
                INSERT INTO Transactions (
                    user_id, amount, payment_method, payment_gateway,
                    transaction_code, qr_code_url, metadata, status, transaction_date
                ) VALUES (?, ?, 'VNPayQR', 'VNPay', ?, ?, ?, 'Pending', NOW())
            ");
            
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=VNPAY-{$txnCode}";
            
            $stmt->execute([
                $rental1['user_id'],
                $finalAmount,
                $txnCode,
                $qrUrl,
                $metadata
            ]);
            
            $txnId = $paymentDb->lastInsertId();
            
            // Link rentals
            $linkStmt = $paymentDb->prepare("
                INSERT INTO RentalPayments (rental_id, transaction_id, amount)
                VALUES (?, ?, ?)
            ");
            $linkStmt->execute([$rental1['rental_id'], $txnId, $rental1['total_cost']]);
            $linkStmt->execute([$rental2['rental_id'], $txnId, $rental2['total_cost']]);
            
            $transactionCount++;
            $rentalPaymentCount += 2;
            
            echo "   ✅ Transaction #{$txnId}: {$txnCode}\n";
            echo "      Rental: #{$rental1['rental_id']}, Amount: " . number_format($rental1['total_cost']) . " VND\n";
            echo "      Rental: #{$rental2['rental_id']}, Amount: " . number_format($rental2['total_cost']) . " VND\n";
            echo "      Total: " . number_format($totalAmount) . " VND\n";
            echo "      Discount: -" . number_format($discountAmount) . " VND (NEW10)\n";
            echo "      Final: " . number_format($finalAmount) . " VND\n\n";
        }
    }
    
    // Transaction 3: Single rental (VNPayQR)
    if (isset($rentals[3])) {
        $rental = $rentals[3];
        
        echo "Creating Transaction 3 (VNPayQR - Single Rental)...\n";
        
        $txnCode = 'TXN-' . date('Ymd') . '-' . str_pad(3, 6, '0', STR_PAD_LEFT);
        $metadata = json_encode([
            'rental_ids' => [$rental['rental_id']],
            'rental_count' => 1,
            'cart_checkout' => false,
            'original_amount' => $rental['total_cost'],
            'discount_amount' => 0
        ]);
        
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=VNPAY-{$txnCode}";
        
        $stmt = $paymentDb->prepare("
            INSERT INTO Transactions (
                user_id, amount, payment_method, payment_gateway,
                transaction_code, qr_code_url, metadata, status, transaction_date
            ) VALUES (?, ?, 'VNPayQR', 'VNPay', ?, ?, ?, 'Pending', NOW())
        ");
        
        $stmt->execute([
            $rental['user_id'],
            $rental['total_cost'],
            $txnCode,
            $qrUrl,
            $metadata
        ]);
        
        $txnId = $paymentDb->lastInsertId();
        
        $linkStmt = $paymentDb->prepare("
            INSERT INTO RentalPayments (rental_id, transaction_id, amount)
            VALUES (?, ?, ?)
        ");
        $linkStmt->execute([$rental['rental_id'], $txnId, $rental['total_cost']]);
        
        $transactionCount++;
        $rentalPaymentCount++;
        
        echo "   ✅ Transaction #{$txnId}: {$txnCode}\n";
        echo "      Rental: #{$rental['rental_id']}, Amount: " . number_format($rental['total_cost']) . " VND\n\n";
    }
    
    $paymentDb->commit();
    
    echo "✅ Seeding complete!\n\n";
    echo "📊 Summary:\n";
    echo "   • Transactions created: {$transactionCount}\n";
    echo "   • Rental-Payment links: {$rentalPaymentCount}\n\n";
    
    echo "🔗 Next steps:\n";
    echo "   1. Login as admin (admin/admin123)\n";
    echo "   2. Go to: http://localhost/admin/transactions.php\n";
    echo "   3. You should see {$transactionCount} pending transactions\n\n";
    
} catch (Exception $e) {
    if ($paymentDb->inTransaction()) {
        $paymentDb->rollback();
    }
    
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}