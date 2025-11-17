<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Profile Fetch</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .debug-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 20px;
        }
        
        h2 {
            color: #764ba2;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .output {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .output.success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .output.error {
            background: #fee;
            color: #991b1b;
        }
        
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .status.success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status.error {
            background: #fee;
            color: #991b1b;
        }
        
        .status.pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .test-step {
            padding: 15px;
            border-left: 4px solid #667eea;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .test-step h3 {
            font-size: 16px;
            margin-bottom: 8px;
            color: #333;
        }
        
        .test-step p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .copy-btn {
            background: #10b981;
            font-size: 12px;
            padding: 6px 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="debug-card">
            <h1>🔍 Debug Profile Fetch Issue</h1>
            
            <div class="test-step">
                <h3>📝 Hướng dẫn sử dụng:</h3>
                <p>1. Nhập token từ localStorage (F12 → Console → localStorage.getItem('token'))</p>
                <p>2. Chọn endpoint cần test</p>
                <p>3. Click "Test Fetch" để kiểm tra</p>
                <p>4. Xem kết quả chi tiết ở phần Output</p>
            </div>
            
            <div class="input-group">
                <label>🎫 Token (từ localStorage):</label>
                <input type="text" id="tokenInput" placeholder="Paste your JWT token here">
                <small style="color: #666; margin-top: 5px; display: block;">
                    Mở Console (F12) và chạy: localStorage.getItem('token')
                </small>
            </div>
            
            <div class="input-group">
                <label>🎯 Test Endpoint:</label>
                <select id="endpointSelect">
                    <option value="/TransportationRenting/gateway/api/profile">GET /profile</option>
                    <option value="/TransportationRenting/gateway/api/auth/login">POST /auth/login (test)</option>
                    <option value="http://localhost:8001/profile">Direct: Customer Service /profile</option>
                    <option value="http://localhost:8001/health">Direct: Customer Service /health</option>
                </select>
            </div>
            
            <div class="input-group">
                <label>🔧 Method:</label>
                <select id="methodSelect">
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="DELETE">DELETE</option>
                </select>
            </div>
            
            <button class="btn" onclick="testFetch()">
                🚀 Test Fetch
            </button>
            <button class="btn btn-secondary" onclick="testAll()">
                🔬 Test All Steps
            </button>
            <button class="btn copy-btn" onclick="copyToken()">
                📋 Copy Token
            </button>
            <button class="btn btn-secondary" onclick="clearOutput()">
                🗑️ Clear
            </button>
        </div>
        
        <div class="debug-card">
            <h2>📊 Test Results</h2>
            <div id="output" class="output">Waiting for test...</div>
        </div>
        
        <div class="debug-card">
            <h2>🔍 Diagnostic Steps</h2>
            <div id="diagnostics"></div>
        </div>
    </div>

    <script>
        function log(message, type = 'info') {
            const output = document.getElementById('output');
            const timestamp = new Date().toLocaleTimeString();
            const prefix = type === 'error' ? '❌' : type === 'success' ? '✅' : 'ℹ️';
            
            output.innerHTML += `[${timestamp}] ${prefix} ${message}\n`;
            output.scrollTop = output.scrollHeight;
            
            if (type === 'success') {
                output.className = 'output success';
            } else if (type === 'error') {
                output.className = 'output error';
            } else {
                output.className = 'output';
            }
        }

        function clearOutput() {
            document.getElementById('output').innerHTML = 'Output cleared.\n';
            document.getElementById('diagnostics').innerHTML = '';
        }

        function copyToken() {
            const token = document.getElementById('tokenInput').value;
            if (token) {
                navigator.clipboard.writeText(token);
                log('Token copied to clipboard', 'success');
            } else {
                log('No token to copy', 'error');
            }
        }

        async function testFetch() {
            clearOutput();
            
            const token = document.getElementById('tokenInput').value.trim();
            const endpoint = document.getElementById('endpointSelect').value;
            const method = document.getElementById('methodSelect').value;
            
            log('=== STARTING FETCH TEST ===');
            log(`Endpoint: ${endpoint}`);
            log(`Method: ${method}`);
            log(`Token length: ${token.length} chars`);
            
            if (!token) {
                log('❌ ERROR: Token is required!', 'error');
                return;
            }
            
            // Validate JWT format
            const parts = token.split('.');
            if (parts.length !== 3) {
                log('❌ ERROR: Invalid JWT format (must have 3 parts)', 'error');
                return;
            }
            
            log('✅ Token format is valid (3 parts)', 'success');
            
            // Decode payload
            try {
                const payload = JSON.parse(atob(parts[1]));
                log('Token payload decoded:', 'info');
                log(JSON.stringify(payload, null, 2));
                
                if (payload.exp) {
                    const expDate = new Date(payload.exp * 1000);
                    const now = new Date();
                    const expired = expDate < now;
                    
                    log(`Token expiration: ${expDate.toLocaleString()}`);
                    log(`Current time: ${now.toLocaleString()}`);
                    log(`Status: ${expired ? '❌ EXPIRED' : '✅ VALID'}`, expired ? 'error' : 'success');
                }
            } catch (e) {
                log('⚠️ Could not decode token payload: ' + e.message);
            }
            
            // Test fetch
            log('\n=== SENDING REQUEST ===');
            
            try {
                const startTime = performance.now();
                
                const response = await fetch(endpoint, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`
                    }
                });
                
                const endTime = performance.now();
                const duration = (endTime - startTime).toFixed(2);
                
                log(`Response status: ${response.status} ${response.statusText}`);
                log(`Response time: ${duration}ms`);
                
                // Log headers
                log('\n=== RESPONSE HEADERS ===');
                for (let [key, value] of response.headers.entries()) {
                    log(`${key}: ${value}`);
                }
                
                // Get response body
                const text = await response.text();
                
                log('\n=== RESPONSE BODY ===');
                log(`Body length: ${text.length} chars`);
                
                try {
                    const data = JSON.parse(text);
                    log(JSON.stringify(data, null, 2));
                    
                    if (response.ok) {
                        log('\n✅ REQUEST SUCCESSFUL', 'success');
                    } else {
                        log('\n❌ REQUEST FAILED', 'error');
                    }
                } catch (e) {
                    log('⚠️ Response is not JSON:');
                    log(text);
                }
                
            } catch (error) {
                log('\n❌ FETCH ERROR:', 'error');
                log(error.message);
                log(error.stack);
            }
        }

        async function testAll() {
            clearOutput();
            
            const diagnostics = document.getElementById('diagnostics');
            diagnostics.innerHTML = '<h3>Running diagnostic tests...</h3>';
            
            const tests = [
                {
                    name: '1. Check Token Existence',
                    test: () => {
                        const token = document.getElementById('tokenInput').value.trim();
                        return token.length > 0 ? 'PASS' : 'FAIL - No token provided';
                    }
                },
                {
                    name: '2. Check Token Format',
                    test: () => {
                        const token = document.getElementById('tokenInput').value.trim();
                        const parts = token.split('.');
                        return parts.length === 3 ? 'PASS' : `FAIL - Has ${parts.length} parts (need 3)`;
                    }
                },
                {
                    name: '3. Check Token Expiration',
                    test: () => {
                        try {
                            const token = document.getElementById('tokenInput').value.trim();
                            const parts = token.split('.');
                            const payload = JSON.parse(atob(parts[1]));
                            
                            if (!payload.exp) return 'WARN - No expiration';
                            
                            const expDate = new Date(payload.exp * 1000);
                            const now = new Date();
                            
                            return expDate > now ? 'PASS' : `FAIL - Expired at ${expDate.toLocaleString()}`;
                        } catch (e) {
                            return 'ERROR - ' + e.message;
                        }
                    }
                },
                {
                    name: '4. Check Gateway Health',
                    test: async () => {
                        try {
                            const response = await fetch('/TransportationRenting/gateway/api/health', {
                                method: 'GET',
                                headers: { 'Content-Type': 'application/json' }
                            });
                            
                            return response.ok ? 'PASS' : `FAIL - Status ${response.status}`;
                        } catch (e) {
                            return 'ERROR - ' + e.message;
                        }
                    }
                },
                {
                    name: '5. Check Customer Service Direct',
                    test: async () => {
                        try {
                            const response = await fetch('http://localhost:8001/health');
                            return response.ok ? 'PASS' : `FAIL - Status ${response.status}`;
                        } catch (e) {
                            return 'ERROR - Service not running';
                        }
                    }
                },
                {
                    name: '6. Test Profile Fetch via Gateway',
                    test: async () => {
                        try {
                            const token = document.getElementById('tokenInput').value.trim();
                            const response = await fetch('/TransportationRenting/gateway/api/profile', {
                                method: 'GET',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Authorization': `Bearer ${token}`
                                }
                            });
                            
                            const text = await response.text();
                            
                            if (response.ok) {
                                return 'PASS - Got profile data';
                            } else {
                                return `FAIL - ${response.status}: ${text.substring(0, 100)}`;
                            }
                        } catch (e) {
                            return 'ERROR - ' + e.message;
                        }
                    }
                }
            ];
            
            let html = '';
            
            for (let test of tests) {
                log(`Running: ${test.name}`);
                
                let result;
                if (test.test.constructor.name === 'AsyncFunction') {
                    result = await test.test();
                } else {
                    result = test.test();
                }
                
                const status = result.startsWith('PASS') ? 'success' : 
                              result.startsWith('WARN') ? 'pending' : 'error';
                
                html += `
                    <div class="test-step">
                        <h3>${test.name} <span class="status ${status}">${result}</span></h3>
                    </div>
                `;
                
                log(`Result: ${result}`, status === 'success' ? 'success' : 'error');
            }
            
            diagnostics.innerHTML = html;
            log('\n=== ALL TESTS COMPLETED ===');
        }

        // Auto-load token from localStorage if available
        window.addEventListener('DOMContentLoaded', () => {
            const storedToken = localStorage.getItem('token');
            if (storedToken) {
                document.getElementById('tokenInput').value = storedToken;
                log('✅ Token auto-loaded from localStorage', 'success');
            } else {
                log('⚠️ No token found in localStorage');
            }
        });
    </script>
</body>
</html>