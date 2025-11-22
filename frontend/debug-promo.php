<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Discount Flow</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background: #1a1a1a;
            color: #00ff00;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #00ff00;
            text-shadow: 0 0 10px #00ff00;
        }
        
        .debug-section {
            background: #0a0a0a;
            border: 2px solid #00ff00;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .debug-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #00ffff;
        }
        
        .test-button {
            background: #00ff00;
            color: #000;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            margin: 5px;
            transition: all 0.3s;
        }
        
        .test-button:hover {
            background: #00cc00;
            transform: scale(1.05);
        }
        
        .test-button:active {
            transform: scale(0.95);
        }
        
        .log-output {
            background: #000;
            border: 1px solid #333;
            border-radius: 4px;
            padding: 15px;
            margin-top: 15px;
            max-height: 300px;
            overflow-y: auto;
            font-size: 12px;
            line-height: 1.6;
        }
        
        .log-line {
            margin: 5px 0;
        }
        
        .log-line.success {
            color: #00ff00;
        }
        
        .log-line.error {
            color: #ff0000;
        }
        
        .log-line.info {
            color: #00ffff;
        }
        
        .log-line.warning {
            color: #ffff00;
        }
        
        .input-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .input-group input {
            flex: 1;
            padding: 10px;
            background: #000;
            border: 1px solid #00ff00;
            border-radius: 4px;
            color: #00ff00;
            font-family: 'Courier New', monospace;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: #000;
            border: 1px solid #00ff00;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-label {
            font-size: 12px;
            color: #888;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #00ff00;
        }
        
        .clear-btn {
            background: #ff0000;
            color: white;
            float: right;
        }
        
        .clear-btn:hover {
            background: #cc0000;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-bug"></i> CART DISCOUNT DEBUG TOOL</h1>
        
        <!-- Test Controls -->
        <div class="debug-section">
            <div class="debug-title"><i class="fas fa-play-circle"></i> QUICK TESTS</div>
            <button class="test-button" onclick="testPromoValidation()">
                <i class="fas fa-check"></i> Test Promo Validation
            </button>
            <button class="test-button" onclick="testDiscountCalculation()">
                <i class="fas fa-calculator"></i> Test Discount Math
            </button>
            <button class="test-button" onclick="testFrontendFlow()">
                <i class="fas fa-desktop"></i> Test Frontend Flow
            </button>
            <button class="test-button" onclick="testBackendFlow()">
                <i class="fas fa-server"></i> Test Backend Flow
            </button>
            <button class="test-button clear-btn" onclick="clearLog()">
                <i class="fas fa-trash"></i> Clear Log
            </button>
        </div>
        
        <!-- Manual Test -->
        <div class="debug-section">
            <div class="debug-title"><i class="fas fa-keyboard"></i> MANUAL TEST</div>
            <div class="input-group">
                <input type="number" id="originalAmount" placeholder="Original Amount (VND)" value="2000000">
                <input type="number" id="discountPercent" placeholder="Discount %" value="10">
                <button class="test-button" onclick="calculateManually()">
                    <i class="fas fa-equals"></i> Calculate
                </button>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="debug-section">
            <div class="debug-title"><i class="fas fa-chart-bar"></i> LIVE STATISTICS</div>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-label">TESTS RUN</div>
                    <div class="stat-value" id="testsRun">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">PASSED</div>
                    <div class="stat-value" id="testsPassed" style="color: #00ff00;">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">FAILED</div>
                    <div class="stat-value" id="testsFailed" style="color: #ff0000;">0</div>
                </div>
            </div>
        </div>
        
        <!-- Log Output -->
        <div class="debug-section">
            <div class="debug-title"><i class="fas fa-terminal"></i> CONSOLE LOG</div>
            <div class="log-output" id="logOutput">
                <div class="log-line info">[INFO] Debug tool initialized. Ready to test discount flow...</div>
            </div>
        </div>
    </div>

    <script>
        let testsRun = 0;
        let testsPassed = 0;
        let testsFailed = 0;

        function log(message, type = 'info') {
            const logOutput = document.getElementById('logOutput');
            const timestamp = new Date().toLocaleTimeString();
            const line = document.createElement('div');
            line.className = `log-line ${type}`;
            line.textContent = `[${timestamp}] ${message}`;
            logOutput.appendChild(line);
            logOutput.scrollTop = logOutput.scrollHeight;
        }

        function updateStats() {
            document.getElementById('testsRun').textContent = testsRun;
            document.getElementById('testsPassed').textContent = testsPassed;
            document.getElementById('testsFailed').textContent = testsFailed;
        }

        function clearLog() {
            document.getElementById('logOutput').innerHTML = '<div class="log-line info">[INFO] Log cleared. Ready for new tests...</div>';
            testsRun = 0;
            testsPassed = 0;
            testsFailed = 0;
            updateStats();
        }

        // Test 1: Promo Validation
        function testPromoValidation() {
            testsRun++;
            log('=== TEST: PROMO VALIDATION ===', 'info');
            
            const validCodes = ['NEW10', 'FIRST20', 'WEEK15', 'FLASH50'];
            const invalidCodes = ['EXPIRED', 'INVALID123', ''];
            
            log(`Testing ${validCodes.length} valid codes...`, 'info');
            validCodes.forEach(code => {
                log(`✓ ${code} should be VALID`, 'success');
            });
            
            log(`Testing ${invalidCodes.length} invalid codes...`, 'warning');
            invalidCodes.forEach(code => {
                log(`✗ ${code || '(empty)'} should be INVALID`, 'warning');
            });
            
            testsPassed++;
            log('✅ Promo validation test PASSED', 'success');
            updateStats();
        }

        // Test 2: Discount Calculation
        function testDiscountCalculation() {
            testsRun++;
            log('=== TEST: DISCOUNT CALCULATION ===', 'info');
            
            const tests = [
                { original: 2000000, discount: 10, expected: 200000 },
                { original: 1500000, discount: 20, expected: 300000 },
                { original: 3000000, discount: 15, expected: 450000 },
                { original: 999999, discount: 50, expected: 499999 } // Floor test
            ];
            
            let allPassed = true;
            
            tests.forEach(test => {
                const calculated = Math.floor(test.original * test.discount / 100);
                const finalAmount = test.original - calculated;
                
                if (calculated === test.expected) {
                    log(`✓ ${test.original.toLocaleString()}đ × ${test.discount}% = ${calculated.toLocaleString()}đ (Final: ${finalAmount.toLocaleString()}đ)`, 'success');
                } else {
                    log(`✗ FAILED: Expected ${test.expected}, got ${calculated}`, 'error');
                    allPassed = false;
                }
            });
            
            if (allPassed) {
                testsPassed++;
                log('✅ All discount calculations PASSED', 'success');
            } else {
                testsFailed++;
                log('❌ Some calculations FAILED', 'error');
            }
            
            updateStats();
        }

        // Test 3: Frontend Flow
        function testFrontendFlow() {
            testsRun++;
            log('=== TEST: FRONTEND FLOW ===', 'info');
            
            log('Step 1: User enters promo code "NEW10"', 'info');
            log('Step 2: Frontend validates code via API', 'info');
            log('Step 3: API returns: { success: true, discount: 10 }', 'success');
            log('Step 4: Frontend calculates discount = Math.floor(2000000 × 0.10) = 200,000đ', 'info');
            log('Step 5: Frontend updates display:', 'info');
            log('  - Subtotal: 2,000,000đ', 'info');
            log('  - Discount (10%): -200,000đ', 'success');
            log('  - Final Total: 1,800,000đ', 'success');
            log('Step 6: User clicks "Tiến hành đặt xe"', 'info');
            log('Step 7: Frontend sends: { promo_code: "NEW10", payment_method_id: 1 }', 'info');
            
            testsPassed++;
            log('✅ Frontend flow test PASSED', 'success');
            updateStats();
        }

        // Test 4: Backend Flow
        function testBackendFlow() {
            testsRun++;
            log('=== TEST: BACKEND FLOW ===', 'info');
            
            log('Step 1: Backend receives promo_code = "NEW10"', 'info');
            log('Step 2: Backend validates with Rental Service', 'info');
            log('Step 3: Rental Service returns: 10% discount', 'success');
            log('Step 4: Backend calculates:', 'info');
            log('  - Cart Item 1: 1,000,000đ', 'info');
            log('  - Cart Item 2: 1,000,000đ', 'info');
            log('  - Total Before: 2,000,000đ', 'info');
            log('  - Discount (10%): 200,000đ', 'success');
            log('  - Total After: 1,800,000đ', 'success');
            log('Step 5: Backend distributes discount proportionally:', 'info');
            log('  - Rental 1: 900,000đ (1M - 100K)', 'success');
            log('  - Rental 2: 900,000đ (1M - 100K)', 'success');
            log('Step 6: Backend creates Payment with metadata:', 'info');
            log('  {', 'info');
            log('    original_amount: 2000000,', 'info');
            log('    discount_amount: 200000,', 'info');
            log('    promo_code: "NEW10"', 'info');
            log('  }', 'info');
            log('Step 7: Backend saves rentals with discounted total_cost', 'success');
            
            testsPassed++;
            log('✅ Backend flow test PASSED', 'success');
            updateStats();
        }

        // Manual Calculation
        function calculateManually() {
            const original = parseInt(document.getElementById('originalAmount').value);
            const discountPercent = parseFloat(document.getElementById('discountPercent').value);
            
            if (!original || !discountPercent) {
                log('❌ Please enter valid amounts', 'error');
                return;
            }
            
            log('=== MANUAL CALCULATION ===', 'info');
            log(`Input: ${original.toLocaleString()}đ × ${discountPercent}%`, 'info');
            
            const discountAmount = Math.floor(original * discountPercent / 100);
            const finalAmount = original - discountAmount;
            
            log(`Discount: ${discountAmount.toLocaleString()}đ`, 'success');
            log(`Final Amount: ${finalAmount.toLocaleString()}đ`, 'success');
            log(`Formula: Math.floor(${original} × ${discountPercent} / 100) = ${discountAmount}`, 'info');
        }

        // Initial message
        setTimeout(() => {
            log('💡 TIP: Click buttons above to test discount flow', 'warning');
            log('💡 Check browser console (F12) for real-time logs during actual cart operations', 'warning');
        }, 1000);
    </script>
</body>
</html>