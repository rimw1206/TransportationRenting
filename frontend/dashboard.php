<?php
// frontend/public/dashboard.php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];
?>

<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/dashboard_style.css">
</head>
<style>
    /* Thêm vào dashboard_style.css */
.modal {
    display: none; 
    position: fixed; 
    z-index: 1000; 
    left: 0;
    top: 0;
    width: 100%;
    height: 100%; 
    overflow: auto; 
    background-color: rgba(0,0,0,0.5); 
}

.modal-content {
    background-color: #fefefe;
    margin: 15% auto; 
    padding: 20px;
    border-radius: 8px;
    width: 400px;
    max-width: 90%;
    text-align: center;
    position: relative;
}

.close-btn {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 1.5rem;
    cursor: pointer;
}

#otpInput {
    width: 80%;
    padding: 10px;
    margin: 15px 0;
    font-size: 1.1rem;
    text-align: center;
}

#confirmOtpBtn {
    padding: 10px 20px;
    font-size: 1rem;
    cursor: pointer;
    background-color: #007bff;
    color: #fff;
    border: none;
    border-radius: 5px;
}

.otp-error {
    color: red;
    font-size: 0.9rem;
    display: none;
    margin-bottom: 10px;
}
/* Thêm vào sau các style hiện tại */
.paid-amount {
    text-decoration: line-through !important;
    color: #999 !important;
}

.paid-warning {
    background-color: #fff3cd;
    border: 1px solid #ffc107;
    color: #856404;
    padding: 12px;
    border-radius: 5px;
    margin: 10px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
}

.paid-warning i {
    font-size: 1.2rem;
    color: #ffc107;
}
</style>
<body>
       <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-university"></i>
                <span>TDTU iBanking</span>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <h3 id="userName"><?= htmlspecialchars($user['fullname']) ?></h3>
                    <p id="userEmail"><?= htmlspecialchars($user['email']) ?></p>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Đăng xuất</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-container">
        <!-- Welcome Section -->
        <section class="welcome-section">
            <h1 id="welcomeMessage">Xin chào, <?= htmlspecialchars($user['username']) ?>!</h1>
            <p>Chào mừng bạn đến với hệ thống thanh toán học phí TDTU. Bạn có thể thực hiện các giao dịch một cách an toàn và tiện lợi.</p>
        </section>

        <!-- Balance Card - Số dư -->
        <section class="balance-card">
            <div class="balance-header">
                <span class="balance-title">Số dư khả dụng</span>
            </div>
            <div class="balance-amount" id="userBalance"><?= number_format($user['balance'], 0, ',', '.') ?> VNĐ</div>
            <div class="card-info">Số dư hiện tại trong tài khoản của bạn</div>
        </section>

        <!-- Account Info Card -->
        <section class="balance-card">
            <div class="balance-header">
                <span class="balance-title" style="font-size: 1.5rem;">Thông tin tài khoản</span>
            </div>
            <div class="card-info" style="margin-bottom: 20px;">Thông tin tài khoản cá nhân và cài đặt bảo mật</div>
            <div>
                <div style="font-size: 1.1rem; margin-top: 10px;">
                    <strong>Họ và tên:</strong> <?= htmlspecialchars($user['fullname']) ?>
                <div style="font-size: 1.1rem; margin-top: 5px;">
                    <strong>Số điện thoại:</strong> <?= htmlspecialchars($user['phone'] ?? 'Chưa cập nhật') ?>
                <div style="font-size: 1.1rem; margin-top: 5px;">
                    <strong>Mã số sinh viên:</strong> <?= htmlspecialchars($user['username'] ?? 'Chưa cập nhật') ?>
                <div style="font-size: 1.1rem; margin-top: 5px;">
                    <strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? 'Chưa cập nhật') ?>
            </div>
        </section>
                <!-- Payment Form Section -->
        <section class="payment-form-section show" id="paymentFormSection">
            <div class="form-header">
                <h2><i class="fas fa-credit-card"></i> Form Thanh Toán Học Phí</h2>
                <p>Vui lòng điền đầy đủ thông tin để thực hiện giao dịch</p>
            </div>

            <form id="paymentForm">
                <!-- Phần 1: Thông tin người nộp tiền -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-user"></i>
                        Thông tin người nộp tiền
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="payerName">Họ và tên</label>
                            <input type="text" id="payerName" value="<?= htmlspecialchars($user['fullname']) ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="payerPhone">Số điện thoại</label>
                            <input type="tel" id="payerPhone" value="<?= htmlspecialchars($user['phone'] ?? 'Chưa cập nhật') ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="payerEmail">Địa chỉ email</label>
                        <input type="email" id="payerEmail" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    </div>
                </div>

                <!-- Phần 2: Thông tin học phí -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-graduation-cap"></i>
                        Thông tin học phí
                    </h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="studentId">Mã số sinh viên *</label>
                            <input type="text" id="studentId" placeholder="Nhập mã số sinh viên" required>
                        </div>
                        <div class="form-group">
                            <button type="button" class="search-btn" onclick="searchStudent()">
                                <i class="fas fa-search"></i>
                                Tìm kiếm
                            </button>
                        </div>
                    </div>

                    <div class="student-info" id="studentInfo">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="studentName">Họ tên sinh viên</label>
                                <input type="text" id="studentName" disabled>
                            </div>
                            <div class="form-group">
                                <label for="tuitionAmount">Số tiền cần nộp</label>
                                <input type="text" id="tuitionAmount" disabled>
                            </div>
                        </div>
                    </div>

                    <div class="error-message" id="errorMessage">
                        <i class="fas fa-exclamation-circle"></i>
                        <span id="errorText">Không tìm thấy sinh viên với mã số này!</span>
                    </div>
                </div>

                <!-- Phần 3: Thông tin thanh toán -->
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-money-bill-wave"></i>
                        Thông tin thanh toán
                    </h3>

                    <div class="payment-summary">
                        <div class="summary-row">
                            <span>Số dư khả dụng:</span>
                            <span class="amount" id="availableBalance"><?= number_format($user['balance'], 0, ',', '.') ?> VNĐ</span>
                        </div>
                        <div class="summary-row">
                            <span>Số tiền học phí:</span>
                            <span class="amount" id="paymentAmount">0 VNĐ</span>
                        </div>
                        <div class="summary-row">
                            <span>Số dư sau giao dịch:</span>
                            <span class="amount" id="remainingBalance"><?= number_format($user['balance'], 0, ',', '.') ?> VNĐ</span>
                        </div>
                    </div>

                    <div class="terms-section">
                        <h4><i class="fas fa-file-contract"></i> Điều khoản và Thỏa thuận</h4>
                        <ul style="margin: 10px 0; padding-left: 20px; line-height: 1.6;">
                            <li>Giao dịch thanh toán học phí không thể hoàn tác sau khi xác nhận</li>
                            <li>Hệ thống chỉ cho phép thanh toán toàn bộ số tiền học phí</li>
                            <li>Thông tin giao dịch sẽ được lưu trữ và bảo mật theo quy định</li>
                            <li>Liên hệ hotline 1900-xxxx khi cần hỗ trợ</li>
                        </ul>
                        
                        <div class="terms-checkbox">
                            <input type="checkbox" id="agreeTerms" onchange="validateForm()">
                            <label for="agreeTerms">
                                Tôi đã đọc và đồng ý với các điều khoản, thỏa thuận trên. 
                                Tôi xác nhận thông tin thanh toán là chính xác và chịu trách nhiệm về giao dịch này.
                            </label>
                        </div>
                    </div>

                    <div class="error-message" id="balanceError">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Số dư không đủ để thực hiện giao dịch!</span>
                    </div>

                    <div class="success-message" id="successMessage">
                        <i class="fas fa-check-circle"></i>
                        <span>Giao dịch đã được thực hiện thành công!</span>
                    </div>
                </div>

                <button type="button" class="confirm-btn" id="confirmBtn" onclick="processPayment()" disabled>
                    <i class="fas fa-lock"></i>
                    Xác nhận thanh toán
                </button>
            </form>
        </section>

        <!-- Recent Transactions -->
        <section class="recent-section">
            <div class="section-header">
                <h2><i class="fas fa-clock"></i> Giao dịch gần đây</h2>
                <a href="history.php" class="view-all">Xem tất cả</a>
            </div>
            
            <div class="transaction-list" id="recentTransactions">
                <!-- Sample transaction for display -->
                <div class="transaction-item">
                    <div class="transaction-info">
                        <div class="transaction-title">Thanh toán học phí kỳ 1/2024</div>
                        <div class="transaction-date">15/09/2024 - 14:30</div>
                    </div>
                    <div class="transaction-amount">-2,500,000 VNĐ</div>
                </div>
                
                <div class="transaction-item">
                    <div class="transaction-info">
                        <div class="transaction-title">Nạp tiền vào tài khoản</div>
                        <div class="transaction-date">10/09/2024 - 09:15</div>
                    </div>
                    <div class="transaction-amount" style="color: #28a745;">+5,000,000 VNĐ</div>
                </div>
                
                <!-- Empty state if no transactions -->
                <!-- 
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <p>Chưa có giao dịch nào được thực hiện</p>
                </div>
                -->
            </div>
        </section>
    </main>

    <!-- POP UP -->
    <!-- OTP Modal -->
    <div id="otpModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" id="closeOtp">&times;</span>
            <h2>Nhập OTP</h2>
            <p>Vui lòng nhập mã OTP đã gửi đến tài khoản của bạn để xác nhận giao dịch.</p>
            <input type="text" id="otpInput" placeholder="Nhập OTP" maxlength="6">
            <div class="otp-error" id="otpError">OTP không hợp lệ hoặc đã hết hạn!</div>
            <button id="confirmOtpBtn">Xác nhận</button>
        </div>
    </div>

    <script>
        const userID = <?= json_encode($user['userID']) ?>;
        let availableBalance = <?= $user['balance'] ?>;
        // Thêm biến global để lưu invoiceID
        let currentInvoiceID = null;

        function formatCurrency(amount) {
            return new Intl.NumberFormat('vi-VN').format(amount) + ' VNĐ';
        }

        function showError(message) {
            document.getElementById('errorText').textContent = message;
            document.getElementById('errorMessage').classList.add('show');
        }

        function validateForm() {
            const studentId = document.getElementById('studentId').value.trim();
            const agreeTerms = document.getElementById('agreeTerms').checked;
            const confirmBtn = document.getElementById('confirmBtn');
            const studentInfo = document.getElementById('studentInfo');
            const balanceError = document.getElementById('balanceError');

            const isValidStudent = studentInfo.classList.contains('show');
            const hasSufficientBalance = !balanceError.classList.contains('show');

            confirmBtn.disabled = !(isValidStudent && agreeTerms && hasSufficientBalance);
            confirmBtn.innerHTML = confirmBtn.disabled ? '<i class="fas fa-lock"></i> Xác nhận thanh toán' : '<i class="fas fa-check"></i> Xác nhận thanh toán';
        }

        // Handle session expiration
        function handleSessionExpired(data) {
            alert(data.message || 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                window.location.href = 'index.php';
            }
        }

        function searchStudent() {
            const studentId = document.getElementById('studentId').value.trim();
            const errorMessage = document.getElementById('errorMessage');
            const studentInfo = document.getElementById('studentInfo');
            
            errorMessage.classList.remove('show');
            studentInfo.classList.remove('show');

            if (!studentId) {
                showError('Vui lòng nhập mã số sinh viên!');
                return;
            }

            const btn = document.querySelector('.search-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang tìm...';

            console.log('Searching for student:', studentId);

            fetch(`http://localhost/ThanhToan/gateway/api/payment/students/search?studentId=${studentId}`)
            .then(response => {
                console.log('Search response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Search response:', data);
                
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-search"></i> Tìm kiếm';

                if (data.success === false && data.redirect) {
                    handleSessionExpired(data);
                    return;
                }

                if (data.success && data.invoices && data.invoices.length > 0) {
                    const invoice = data.invoices[0];
                    const student = data.student;

                    currentInvoiceID = invoice.invoiceID;

                    document.getElementById('studentName').value = student.fullname;
                    const tuitionAmount = invoice.amount || invoice.debt || 0;
                    
                    //  KIỂM TRA TRẠNG THÁI THANH TOÁN
                    const isPaid = invoice.status === 'Thanh toán' || invoice.status === 'Đã thanh toán';
                    
                    if (isPaid) {
                        // Hiển thị số tiền gạch ngang
                        document.getElementById('tuitionAmount').value = formatCurrency(tuitionAmount);
                        document.getElementById('tuitionAmount').style.textDecoration = 'line-through';
                        document.getElementById('tuitionAmount').style.color = '#999';
                        
                        document.getElementById('paymentAmount').textContent = formatCurrency(tuitionAmount);
                        document.getElementById('paymentAmount').style.textDecoration = 'line-through';
                        document.getElementById('paymentAmount').style.color = '#999';
                        
                        // Thêm thông báo đã thanh toán
                        showError('⚠️ Sinh viên đã thanh toán học phí!');
                        
                        // Disable nút xác nhận
                        document.getElementById('confirmBtn').disabled = true;
                        document.getElementById('agreeTerms').disabled = true;
                        
                        // Không cập nhật số dư sau giao dịch
                        document.getElementById('remainingBalance').textContent = formatCurrency(availableBalance);
                        
                        studentInfo.classList.add('show');
                        
                    } else {
                        // Reset style
                        document.getElementById('tuitionAmount').value = formatCurrency(tuitionAmount);
                        document.getElementById('tuitionAmount').style.textDecoration = 'none';
                        document.getElementById('tuitionAmount').style.color = '';
                        
                        document.getElementById('paymentAmount').textContent = formatCurrency(tuitionAmount);
                        document.getElementById('paymentAmount').style.textDecoration = 'none';
                        document.getElementById('paymentAmount').style.color = '';

                        const remaining = availableBalance - tuitionAmount;
                        document.getElementById('remainingBalance').textContent = formatCurrency(remaining);
                        
                        if (remaining < 0) {
                            document.getElementById('balanceError').classList.add('show');
                        } else {
                            document.getElementById('balanceError').classList.remove('show');
                        }

                        document.getElementById('agreeTerms').disabled = false;
                        studentInfo.classList.add('show');
                        validateForm();
                    }
                    
                } else {
                    showError(data.message || 'Không tìm thấy sinh viên với mã số này hoặc sinh viên đã thanh toán đủ học phí!');
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-search"></i> Tìm kiếm';
                showError('Không thể kết nối tới server! Vui lòng kiểm tra kết nối.');
            });
        }

        function processPayment() {
            const amountText = document.getElementById('paymentAmount').textContent.replace(/\D/g,'');
            const amount = parseInt(amountText);
            
            //CHECK invoiceID
            if (!currentInvoiceID) {
                alert('Vui lòng tìm kiếm sinh viên trước!');
                return;
            }
            
            if (!amount || amount <= 0) {
                alert('Thông tin thanh toán không hợp lệ!');
                return;
            }

            console.log('Creating payment:', { userID, currentInvoiceID, amount });

            fetch('http://localhost/ThanhToan/gateway/api/paymentinformation/create_payment', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                credentials: 'include',
                body: JSON.stringify({
                    payerID: userID,
                    invoiceID: currentInvoiceID,
                    amount: amount
                })
            })
            .then(response => {
                console.log('Create payment response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Create payment response:', data);
                
                if (data.success === false && data.redirect) {
                    handleSessionExpired(data);
                    return;
                }
                
                if (!data.success) {
                    alert(data.message || 'Không thể tạo giao dịch!');
                    return;
                }

                const paymentID = data.paymentID;
                if (!paymentID) {
                    alert('Không nhận được mã giao dịch!');
                    return;
                }

                showOTPModal(paymentID, amount);
            })
            .catch(error => {
                console.error('Create payment error:', error);
                alert('Không thể kết nối tới server! Vui lòng kiểm tra kết nối.');
            });
        }

        function showOTPModal(paymentID, amount) {
            const modal = document.getElementById('otpModal');
            const otpInput = document.getElementById('otpInput');
            const otpError = document.getElementById('otpError');
            
            otpInput.value = '';
            otpError.style.display = 'none';
            modal.style.display = 'block';

            const closeBtn = document.getElementById('closeOtp');
            closeBtn.onclick = () => modal.style.display = 'none';

            const confirmOtpBtn = document.getElementById('confirmOtpBtn');
            confirmOtpBtn.onclick = null;
            confirmOtpBtn.onclick = () => confirmOTP(paymentID, amount);

            window.onclick = event => {
                if (event.target == modal) modal.style.display = "none";
            };
            
            otpInput.onkeypress = (e) => {
                if (e.key === 'Enter') {
                    confirmOTP(paymentID, amount);
                }
            };
        }

        function confirmOTP(paymentID, amount) {
            const otp = document.getElementById('otpInput').value.trim();
            const otpError = document.getElementById('otpError');
            const confirmOtpBtn = document.getElementById('confirmOtpBtn');

            if (!otp || otp.length !== 6 || isNaN(otp)) {
                otpError.textContent = 'Vui lòng nhập OTP gồm 6 chữ số!';
                otpError.style.display = 'block';
                return;
            }
            
            otpError.style.display = 'none';
            confirmOtpBtn.disabled = true;
            confirmOtpBtn.textContent = 'Đang xử lý...';

            console.log('Confirming payment:', { paymentID, otp });

            fetch('http://localhost/ThanhToan/gateway/api/paymentinformation/confirm_payment', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                credentials: 'include',
                body: JSON.stringify({ 
                    paymentID: paymentID, 
                    otp: otp,
                    payerID: userID
                })
            })
            .then(response => {
                console.log('Confirm payment response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Confirm payment response:', data);
                
                confirmOtpBtn.disabled = false;
                confirmOtpBtn.textContent = 'Xác nhận';
                
                if (data.success === false && data.redirect) {
                    handleSessionExpired(data);
                    return;
                }
                
                if (data.success) {
                    document.getElementById('otpModal').style.display = 'none';
                    document.getElementById('successMessage').classList.add('show');

                    const newBalance = data.newBalance;
                    if (newBalance !== undefined && newBalance !== null) {
                        console.log('Updating balance from', availableBalance, 'to', newBalance);
                        
                        // Cập nhật UI
                        document.getElementById('userBalance').textContent = formatCurrency(newBalance);
                        document.getElementById('availableBalance').textContent = formatCurrency(newBalance);
                        document.getElementById('remainingBalance').textContent = formatCurrency(newBalance);

                        availableBalance = newBalance;

                        // Cập nhật session PHP
                        updateSession(newBalance);
                        
                    } else {
                        console.warn('No newBalance in response, calculating manually');
                        const calculatedBalance = availableBalance - amount;
                        
                        // Cập nhật UI
                        document.getElementById('userBalance').textContent = formatCurrency(calculatedBalance);
                        document.getElementById('availableBalance').textContent = formatCurrency(calculatedBalance);
                        document.getElementById('remainingBalance').textContent = formatCurrency(calculatedBalance);
                        
                        availableBalance = calculatedBalance;

                        // Cập nhật session PHP
                        updateSession(calculatedBalance);
                    }

                    resetPaymentForm();
                    loadRecentTransactions();
                    setTimeout(() => {
                        document.getElementById('successMessage').classList.remove('show');
                    }, 3000);
                    
                } else {
                    otpError.textContent = data.message || 'OTP không hợp lệ hoặc đã hết hạn!';
                    otpError.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Confirm payment error:', error);
                confirmOtpBtn.disabled = false;
                confirmOtpBtn.textContent = 'Xác nhận';
                otpError.textContent = 'Không thể kết nối tới server! Vui lòng thử lại.';
                otpError.style.display = 'block';
            });
        }

        function resetPaymentForm() {
            document.getElementById('studentId').value = '';
            document.getElementById('studentName').value = '';
            document.getElementById('tuitionAmount').value = '';
            
            // Reset style 
            document.getElementById('tuitionAmount').style.textDecoration = 'none';
            document.getElementById('tuitionAmount').style.color = '';
            
            document.getElementById('paymentAmount').textContent = '0 VNĐ';
            document.getElementById('paymentAmount').style.textDecoration = 'none';
            document.getElementById('paymentAmount').style.color = '';
            
            document.getElementById('balanceError').classList.remove('show');
            document.getElementById('errorMessage').classList.remove('show');
            document.getElementById('studentInfo').classList.remove('show');
            document.getElementById('agreeTerms').checked = false;
            document.getElementById('agreeTerms').disabled = false;
            currentInvoiceID = null;
            validateForm();
        }
        document.getElementById('studentId').addEventListener('keypress', e => {
            if (e.key === 'Enter') searchStudent();
        });
        function updateSession(newBalance) {
            fetch('update_session.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ newBalance: newBalance })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Session updated successfully');
                } else {
                    console.error('Failed to update session:', data.message);
                }
            })
            .catch(error => {
                console.error('Error updating session:', error);
            });
        }
        // Load lịch sử giao dịch gần đây của user
        function loadRecentTransactions() {
            fetch(`http://localhost/ThanhToan/gateway/api/paymentinformation/history?userID=${userID}`, {
                method: 'GET',
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('recentTransactions');
                
                if (data.success && data.payments && data.payments.length > 0) {
                    let html = '';
                    
                    // Lấy 5 giao dịch gần nhất
                    data.payments.slice(0, 5).forEach(payment => {
                        const amount = payment.debt || payment.amount || 0;
                        const timestamp = new Date(payment.timestamp).toLocaleString('vi-VN', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        
                        let statusText = '';
                        let amountColor = '#dc3545'; // Đỏ cho thanh toán
                        
                        switch(payment.status) {
                            case 'paid':
                                statusText = 'Thanh toán học phí';
                                break;
                            case 'pending':
                                statusText = 'Đang chờ xác nhận';
                                amountColor = '#ffc107';
                                break;
                            case 'cancelled':
                                statusText = 'Giao dịch đã hủy';
                                amountColor = '#6c757d';
                                break;
                            case 'expired':
                                statusText = 'Giao dịch hết hạn';
                                amountColor = '#6c757d';
                                break;
                            default:
                                statusText = 'Giao dịch';
                        }
                        
                        html += `
                            <div class="transaction-item">
                                <div class="transaction-info">
                                    <div class="transaction-title">
                                        ${statusText} - ${payment.invoiceID}
                                    </div>
                                    <div class="transaction-date">${timestamp}</div>
                                    <div style="font-size: 0.85rem; color: #999; margin-top: 2px;">
                                        ${payment.paymentID}
                                    </div>
                                </div>
                                <div class="transaction-amount" style="color: ${amountColor};">
                                    -${formatCurrency(amount)}
                                </div>
                            </div>
                        `;
                    });
                    
                    container.innerHTML = html;
                    
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <p>Chưa có giao dịch nào được thực hiện</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading recent transactions:', error);
                document.getElementById('recentTransactions').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Không thể tải dữ liệu giao dịch</p>
                    </div>
                `;
            });
        }

        // Load khi trang load
        document.addEventListener('DOMContentLoaded', function() {
            loadRecentTransactions();
        });
    </script>
</body>
</html>