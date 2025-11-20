<?php
/**
 * ================================================
 * frontend/api/payment-callback.php
 * VNPay Return URL Handler
 * ================================================
 */

session_start();

require_once __DIR__ . '/../../services/payment/services/PaymentService.php';

// Get VNPay return data
$vnpayData = $_GET;

if (empty($vnpayData)) {
    header('Location: /public/dashboard.php?payment=error&msg=No data received');
    exit;
}

try {
    $paymentService = new PaymentService();
    $result = $paymentService->handleVNPayCallback($vnpayData);
    
    $vnpResponseCode = $vnpayData['vnp_ResponseCode'];
    $transactionCode = $vnpayData['vnp_TxnRef'];
    
    if ($vnpResponseCode === '00') {
        // Payment success
        header("Location: /public/dashboard.php?payment=success&code={$transactionCode}");
    } else {
        // Payment failed
        $errorMessage = getVNPayErrorMessage($vnpResponseCode);
        header("Location: /public/dashboard.php?payment=failed&code={$transactionCode}&msg=" . urlencode($errorMessage));
    }
    
} catch (Exception $e) {
    error_log('Payment callback error: ' . $e->getMessage());
    header('Location: /public/dashboard.php?payment=error&msg=' . urlencode($e->getMessage()));
}

exit;

/**
 * Get VNPay error message
 */
function getVNPayErrorMessage($code) {
    $messages = [
        '00' => 'Giao dịch thành công',
        '07' => 'Trừ tiền thành công. Giao dịch bị nghi ngờ (liên quan tới lừa đảo, giao dịch bất thường).',
        '09' => 'Giao dịch không thành công do: Thẻ/Tài khoản của khách hàng chưa đăng ký dịch vụ InternetBanking tại ngân hàng.',
        '10' => 'Giao dịch không thành công do: Khách hàng xác thực thông tin thẻ/tài khoản không đúng quá 3 lần',
        '11' => 'Giao dịch không thành công do: Đã hết hạn chờ thanh toán. Xin quý khách vui lòng thực hiện lại giao dịch.',
        '12' => 'Giao dịch không thành công do: Thẻ/Tài khoản của khách hàng bị khóa.',
        '13' => 'Giao dịch không thành công do Quý khách nhập sai mật khẩu xác thực giao dịch (OTP).',
        '24' => 'Giao dịch không thành công do: Khách hàng hủy giao dịch',
        '51' => 'Giao dịch không thành công do: Tài khoản của quý khách không đủ số dư để thực hiện giao dịch.',
        '65' => 'Giao dịch không thành công do: Tài khoản của Quý khách đã vượt quá hạn mức giao dịch trong ngày.',
        '75' => 'Ngân hàng thanh toán đang bảo trì.',
        '79' => 'Giao dịch không thành công do: KH nhập sai mật khẩu thanh toán quá số lần quy định.',
        '99' => 'Các lỗi khác (lỗi còn lại, không có trong danh sách mã lỗi đã liệt kê)'
    ];
    
    return $messages[$code] ?? 'Lỗi không xác định';
}