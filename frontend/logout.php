<?php
session_start();

// Nếu có token, gọi API logout để blacklist token
if (isset($_SESSION['token'])) {
    $token = $_SESSION['token'];
    
    try {
        $apiUrl = "http://localhost/TransportationRenting/gateway/api/auth/logout";
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log logout (optional)
        if ($httpCode === 200) {
            error_log('User logged out successfully');
        }
    } catch (Exception $e) {
        error_log('Logout API error: ' . $e->getMessage());
    }
}

// Xóa tất cả session
$_SESSION = array();

// Xóa session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Hủy session
session_destroy();

// Redirect về trang login
header('Location: login.php?logout=success');
exit;
?>