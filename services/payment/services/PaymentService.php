<?php
/**
 * ================================================
 * services/payment/services/PaymentService.php
 * COMPLETE PAYMENT SERVICE - All Methods
 * ================================================
 */

require_once __DIR__ . '/../classes/Payment.php';
require_once __DIR__ . '/../../../shared/classes/ApiClient.php';

class PaymentService {
    private $paymentModel;
    private $apiClient;
    
    // VNPay Configuration
    private $vnpayConfig = [
        'vnp_TmnCode' => '2QXUI4J4',
        'vnp_HashSecret' => 'SECRETKEY123456789',
        'vnp_Url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        'vnp_ReturnUrl' => 'http://localhost/api/payment-callback.php'
    ];
    
    public function __construct() {
        $this->paymentModel = new Payment();
        $this->apiClient = new ApiClient();
        
        $this->apiClient->setServiceUrl('customer', 'http://localhost:8001');
        $this->apiClient->setServiceUrl('rental', 'http://localhost:8003');
    }
    
    /**
     * Validate user via /profile
     */
    private function validateUser($userId, $token = null) {
        try {
            if (!$token) {
                return false;
            }
            
            $response = $this->apiClient->get('customer', '/profile', [
                'Authorization: Bearer ' . $token
            ]);
            
            if ($response['status_code'] === 200) {
                $data = json_decode($response['raw_response'], true);
                
                if ($data && $data['success'] && isset($data['data']['user_id'])) {
                    return (int)$data['data']['user_id'] === (int)$userId;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("validateUser error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process payment (COD or VNPayQR)
     */
    public function processPayment($userId, $data, $token) {
        $required = ['rental_id', 'amount', 'payment_method_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        $rentalId = (int)$data['rental_id'];
        $amount = (float)$data['amount'];
        $paymentMethodId = (int)$data['payment_method_id'];
        
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than 0');
        }
        
        if (!$this->validateUser($userId, $token)) {
            throw new Exception('User validation failed');
        }
        
        $paymentMethod = $this->getPaymentMethod($paymentMethodId, $token);
        
        if ($paymentMethod['user_id'] != $userId) {
            throw new Exception('Payment method does not belong to you');
        }
        
        $rental = $this->verifyRental($rentalId, $userId, $amount, $token);
        
        if ($paymentMethod['type'] === 'COD') {
            return $this->processCODPayment($rentalId, $userId, $amount);
        } elseif ($paymentMethod['type'] === 'VNPayQR') {
            return $this->processVNPayPayment($rentalId, $userId, $amount, $data['return_url'] ?? null);
        } else {
            throw new Exception('Unsupported payment method: ' . $paymentMethod['type']);
        }
    }
    
    /**
     * Get payment method from Customer Service
     */
    private function getPaymentMethod($methodId, $token) {
        $response = $this->apiClient->get('customer', "/payment-methods", [
            'Authorization: Bearer ' . $token
        ]);
        
        if ($response['status_code'] !== 200) {
            throw new Exception('Failed to get payment methods');
        }
        
        $data = json_decode($response['raw_response'], true);
        
        if (!$data['success']) {
            throw new Exception('Failed to get payment method details');
        }
        
        foreach ($data['data'] as $method) {
            if ($method['method_id'] == $methodId) {
                return $method;
            }
        }
        
        throw new Exception('Payment method not found');
    }
    
    /**
     * Verify rental
     */
    private function verifyRental($rentalId, $userId, $amount, $token) {
        $response = $this->apiClient->get('rental', "/rentals/{$rentalId}", [
            'Authorization: Bearer ' . $token
        ]);
        
        if ($response['status_code'] !== 200) {
            throw new Exception('Rental not found');
        }
        
        $data = json_decode($response['raw_response'], true);
        
        if (!$data['success']) {
            throw new Exception('Failed to verify rental');
        }
        
        $rental = $data['data']['rental'] ?? $data['data'];
        
        if ($rental['user_id'] != $userId) {
            throw new Exception('Rental does not belong to you');
        }
        
        if (!in_array($rental['status'], ['Pending', 'Ongoing'])) {
            throw new Exception('Rental cannot be paid (status: ' . $rental['status'] . ')');
        }
        
        if (abs($rental['total_cost'] - $amount) > 0.01) {
            throw new Exception('Amount mismatch. Expected: ' . $rental['total_cost'] . ', Got: ' . $amount);
        }
        
        return $rental;
    }
    
    /**
     * Process COD payment
     */
    private function processCODPayment($rentalId, $userId, $amount) {
        $this->paymentModel->beginTransaction();
        
        try {
            $transactionCode = 'COD-' . strtoupper(uniqid());
            
            $transactionId = $this->paymentModel->createTransaction([
                'rental_id' => $rentalId,
                'user_id' => $userId,
                'amount' => $amount,
                'payment_method' => 'COD',
                'payment_gateway' => null,
                'transaction_code' => $transactionCode,
                'status' => 'Pending'
            ]);
            
            $invoice = $this->paymentModel->createInvoice($transactionId, $amount);
            
            $this->paymentModel->commit();
            
            return [
                'transaction_id' => $transactionId,
                'transaction_code' => $transactionCode,
                'payment_method' => 'COD',
                'status' => 'Pending',
                'message' => 'Đơn hàng đã được tạo. Vui lòng thanh toán khi nhận xe.',
                'invoice' => $invoice
            ];
            
        } catch (Exception $e) {
            $this->paymentModel->rollback();
            throw $e;
        }
    }
    
    /**
     * Process VNPay payment - Generate payment URL
     */
    private function processVNPayPayment($rentalId, $userId, $amount, $returnUrl = null) {
        $this->paymentModel->beginTransaction();
        
        try {
            $transactionCode = 'VNPAY-' . time() . '-' . $rentalId;
            
            $transactionId = $this->paymentModel->createTransaction([
                'rental_id' => $rentalId,
                'user_id' => $userId,
                'amount' => $amount,
                'payment_method' => 'VNPayQR',
                'payment_gateway' => 'VNPay',
                'transaction_code' => $transactionCode,
                'status' => 'Pending'
            ]);
            
            $vnpayUrl = $this->generateVNPayUrl($transactionId, $transactionCode, $amount, $rentalId, $returnUrl);
            
            $invoice = $this->paymentModel->createInvoice($transactionId, $amount);
            
            $this->paymentModel->commit();
            
            return [
                'transaction_id' => $transactionId,
                'transaction_code' => $transactionCode,
                'payment_method' => 'VNPayQR',
                'status' => 'Pending',
                'vnpay_url' => $vnpayUrl,
                'message' => 'Chuyển hướng đến VNPay để thanh toán',
                'expires_in' => 900,
                'invoice' => $invoice
            ];
            
        } catch (Exception $e) {
            $this->paymentModel->rollback();
            throw $e;
        }
    }
    
    /**
     * Generate VNPay payment URL
     */
    private function generateVNPayUrl($transactionId, $transactionCode, $amount, $rentalId, $returnUrl = null) {
        $vnp_TxnRef = $transactionCode;
        $vnp_OrderInfo = "Thanh toan don thue xe #{$rentalId}";
        $vnp_Amount = $amount * 100;
        
        $inputData = [
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $this->vnpayConfig['vnp_TmnCode'],
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            "vnp_Locale" => "vn",
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => "other",
            "vnp_ReturnUrl" => $returnUrl ?? $this->vnpayConfig['vnp_ReturnUrl'],
            "vnp_TxnRef" => $vnp_TxnRef,
            "vnp_ExpireDate" => date('YmdHis', strtotime('+15 minutes'))
        ];
        
        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }
        
        $vnpayUrl = $this->vnpayConfig['vnp_Url'] . "?" . $query;
        
        if (isset($this->vnpayConfig['vnp_HashSecret'])) {
            $vnpSecureHash = hash_hmac('sha512', $hashdata, $this->vnpayConfig['vnp_HashSecret']);
            $vnpayUrl .= 'vnp_SecureHash=' . $vnpSecureHash;
        }
        
        return $vnpayUrl;
    }
    
    /**
     * Handle VNPay callback/IPN
     */
    public function handleVNPayCallback($vnpayData) {
        $vnp_SecureHash = $vnpayData['vnp_SecureHash'] ?? '';
        unset($vnpayData['vnp_SecureHash']);
        unset($vnpayData['vnp_SecureHashType']);
        
        ksort($vnpayData);
        $hashData = "";
        $i = 0;
        
        foreach ($vnpayData as $key => $value) {
            if ($i == 1) {
                $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }
        
        $secureHash = hash_hmac('sha512', $hashData, $this->vnpayConfig['vnp_HashSecret']);
        
        if ($secureHash !== $vnp_SecureHash) {
            throw new Exception('Invalid signature');
        }
        
        $transactionCode = $vnpayData['vnp_TxnRef'];
        $transaction = $this->paymentModel->getTransactionByCode($transactionCode);
        
        if (!$transaction) {
            throw new Exception('Transaction not found');
        }
        
        $vnpResponseCode = $vnpayData['vnp_ResponseCode'];
        
        if ($vnpResponseCode === '00') {
            $this->paymentModel->updateTransactionStatus($transaction['transaction_id'], 'Success');
            return 'success';
        } else {
            $this->paymentModel->updateTransactionStatus($transaction['transaction_id'], 'Failed');
            return 'failed';
        }
    }
    
    /**
     * Verify payment (Admin manually confirms)
     */
    public function verifyPayment($transactionId, $token) {
        $transaction = $this->paymentModel->getTransactionById($transactionId);
        
        if (!$transaction) {
            throw new Exception('Transaction not found');
        }
        
        if ($transaction['status'] === 'Success') {
            throw new Exception('Transaction already verified');
        }
        
        $this->paymentModel->updateTransactionStatus($transactionId, 'Success');
        
        return [
            'transaction_id' => $transactionId,
            'status' => 'Success',
            'verified_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get transaction details
     */
    public function getTransaction($transactionId, $userId) {
        $transaction = $this->paymentModel->getTransactionById($transactionId, $userId);
        
        if (!$transaction) {
            throw new Exception('Transaction not found');
        }
        
        return $transaction;
    }
    
    /**
     * Get user transactions
     */
    public function getUserTransactions($userId, $filters = [], $page = 1, $perPage = 20) {
        return $this->paymentModel->getUserTransactions($userId, $filters, $page, $perPage);
    }
    
    /**
     * Get invoice
     */
    public function getInvoice($invoiceId, $userId) {
        $invoice = $this->paymentModel->getInvoiceById($invoiceId, $userId);
        
        if (!$invoice) {
            throw new Exception('Invoice not found');
        }
        
        return $invoice;
    }
    
    /**
     * Request refund
     */
    public function requestRefund($userId, $data) {
        $required = ['transaction_id', 'reason'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        $transactionId = (int)$data['transaction_id'];
        $transaction = $this->paymentModel->getTransactionById($transactionId, $userId);
        
        if (!$transaction) {
            throw new Exception('Transaction not found');
        }
        
        if ($transaction['status'] !== 'Success') {
            throw new Exception('Can only refund successful transactions');
        }
        
        if ($this->paymentModel->hasActiveRefund($transactionId)) {
            throw new Exception('Refund request already exists for this transaction');
        }
        
        $refundId = $this->paymentModel->createRefund([
            'transaction_id' => $transactionId,
            'amount' => $transaction['amount'],
            'reason' => $data['reason'],
            'refund_method' => $transaction['payment_gateway'] ?? 'Cash'
        ]);
        
        return [
            'refund_id' => $refundId,
            'status' => 'Pending',
            'message' => 'Refund request submitted successfully'
        ];
    }
    
    /**
     * Get refund details
     */
    public function getRefund($refundId, $userId) {
        $refund = $this->paymentModel->getRefundById($refundId, $userId);
        
        if (!$refund) {
            throw new Exception('Refund not found');
        }
        
        return $refund;
    }
}