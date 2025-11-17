<?php
/**
 * shared/classes/EmailService.php
 * FINAL FIX - Load autoloader with absolute path resolution
 */

if (class_exists('EmailService')) {
    return;
}

// STEP 1: Find project root by looking for composer.json
function findProjectRoot($startDir) {
    $currentDir = $startDir;
    $maxLevels = 10;
    $level = 0;
    
    while ($level < $maxLevels) {
        if (file_exists($currentDir . '/composer.json')) {
            return $currentDir;
        }
        
        $parentDir = dirname($currentDir);
        if ($parentDir === $currentDir) {
            break;
        }
        
        $currentDir = $parentDir;
        $level++;
    }
    
    return null;
}

// STEP 2: Load autoloader from project root
$projectRoot = findProjectRoot(__DIR__);

if ($projectRoot) {
    $autoloadPath = $projectRoot . '/vendor/autoload.php';
    
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    } else {
        error_log("EmailService: vendor/autoload.php not found at: $autoloadPath");
        throw new Exception('Composer dependencies not installed. Run: composer install');
    }
} else {
    error_log("EmailService: Could not find project root (composer.json) from: " . __DIR__);
    throw new Exception('Could not locate project root. Ensure composer.json exists.');
}

// STEP 3: Verify PHPMailer is available
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    error_log('EmailService: PHPMailer class not found after loading autoloader');
    throw new Exception('PHPMailer not installed. Run: composer require phpmailer/phpmailer');
}

class EmailService
{
    private $mailer;
    private $fromAddress;
    private $fromName;
    private $appUrl;
    
    public function __construct()
    {
        // Load env
        if (!defined('ENV_BOOTSTRAP_LOADED')) {
            $projectRoot = findProjectRoot(__DIR__);
            if ($projectRoot) {
                $envPath = $projectRoot . '/env-bootstrap.php';
                if (file_exists($envPath)) {
                    require_once $envPath;
                }
            }
        }
        
        // Create PHPMailer instance using fully qualified name
        $this->mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $this->fromAddress = getenv('MAIL_FROM_ADDRESS') ?: 'noreply@transportation.com';
        $this->fromName = getenv('MAIL_FROM_NAME') ?: 'Transportation Renting';
        $this->appUrl = getenv('APP_URL') ?: 'http://localhost/TransportationRenting';
        
        $this->configureSMTP();
    }
    
    private function configureSMTP()
    {
        try {
            $driver = getenv('MAIL_DRIVER') ?: 'smtp';
            
            if ($driver === 'smtp' || $driver === 'gmail') {
                $this->mailer->isSMTP();
                $this->mailer->Host = getenv('MAIL_HOST') ?: 'smtp.gmail.com';
                $this->mailer->SMTPAuth = true;
                $this->mailer->Username = getenv('MAIL_USERNAME');
                $this->mailer->Password = getenv('MAIL_PASSWORD');
                $this->mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $this->mailer->Port = (int)(getenv('MAIL_PORT') ?: 587);
                $this->mailer->Timeout = (int)(getenv('MAIL_TIMEOUT') ?: 30);
                
                if (getenv('MAIL_DEBUG') === 'true') {
                    $this->mailer->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
                    $this->mailer->Debugoutput = function($str, $level) {
                        error_log("SMTP Debug [$level]: $str");
                    };
                }
            }
            
            $this->mailer->setFrom($this->fromAddress, $this->fromName);
            $this->mailer->CharSet = 'UTF-8';
            
        } catch (\Exception $e) {
            error_log("EmailService::configureSMTP error: " . $e->getMessage());
            throw new \Exception("Failed to configure email service");
        }
    }
    
    public function sendVerificationEmail($email, $token, $name = '')
    {
        try {
            $verificationLink = "{$this->appUrl}/frontend/verify-email.php?token={$token}";
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email, $name);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Xác thực tài khoản - Transportation Renting';
            $this->mailer->Body = $this->getVerificationEmailTemplate($name, $verificationLink);
            $this->mailer->AltBody = $this->getVerificationEmailPlainText($name, $verificationLink);
            
            $result = $this->mailer->send();
            
            if ($result) {
                error_log("✅ Verification email sent to: {$email}");
            }
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("❌ Failed to send verification email: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendPasswordResetEmail($email, $token, $name = '')
    {
        try {
            $resetLink = "{$this->appUrl}/frontend/reset-password.php?token={$token}";
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email, $name);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Đặt lại mật khẩu - Transportation Renting';
            $this->mailer->Body = $this->getPasswordResetTemplate($name, $resetLink);
            $this->mailer->AltBody = $this->getPasswordResetPlainText($name, $resetLink);
            
            return $this->mailer->send();
            
        } catch (\Exception $e) {
            error_log("Failed to send password reset email: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendWelcomeEmail($email, $name)
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email, $name);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Chào mừng đến với Transportation Renting!';
            $this->mailer->Body = $this->getWelcomeEmailTemplate($name);
            $this->mailer->AltBody = $this->getWelcomeEmailPlainText($name);
            
            return $this->mailer->send();
            
        } catch (\Exception $e) {
            error_log("Failed to send welcome email: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendBookingConfirmation($email, $bookingData)
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email, $bookingData['customer_name'] ?? '');
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Xác nhận đặt xe - Transportation Renting';
            $this->mailer->Body = $this->getBookingConfirmationTemplate($bookingData);
            $this->mailer->AltBody = $this->getBookingConfirmationPlainText($bookingData);
            
            return $this->mailer->send();
            
        } catch (\Exception $e) {
            error_log("Failed to send booking confirmation: " . $e->getMessage());
            return false;
        }
    }
    
    public function send($to, $subject, $htmlBody, $plainBody = '')
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->isHTML(!empty($htmlBody));
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;
            
            if ($plainBody) {
                $this->mailer->AltBody = $plainBody;
            }
            
            return $this->mailer->send();
            
        } catch (\Exception $e) {
            error_log("Failed to send email: " . $e->getMessage());
            return false;
        }
    }
    
    public function testConnection($testEmail = null)
    {
        try {
            if (!$testEmail) {
                $testEmail = getenv('MAIL_USERNAME');
            }
            
            if (!$this->mailer->smtpConnect()) {
                return [
                    'success' => false,
                    'message' => 'SMTP connection failed'
                ];
            }
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($testEmail);
            $this->mailer->Subject = 'Test Email - Transportation Renting';
            $this->mailer->Body = '<h1>Test Email</h1><p>If you receive this, email configuration is working!</p>';
            
            $sent = $this->mailer->send();
            
            return [
                'success' => $sent,
                'message' => $sent ? 'Test email sent successfully' : 'Failed to send test email'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    private function getVerificationEmailTemplate($name, $link)
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xác thực tài khoản</title>
</head>
<body style="margin:0;padding:0;font-family:Arial,sans-serif;background-color:#f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:40px 20px;text-align:center;">
                            <h1 style="margin:0;color:#ffffff;font-size:28px;">🚗 Transportation Renting</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:40px 30px;">
                            <h2 style="color:#333;margin:0 0 20px;">Xin chào {$name}!</h2>
                            <p style="color:#555;font-size:16px;line-height:1.6;margin:0 0 20px;">
                                Cảm ơn bạn đã đăng ký tài khoản tại Transportation Renting. 
                                Để hoàn tất quá trình đăng ký, vui lòng xác thực địa chỉ email của bạn.
                            </p>
                            <div style="text-align:center;margin:30px 0;">
                                <a href="{$link}" 
                                   style="display:inline-block;padding:15px 40px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#ffffff;text-decoration:none;border-radius:50px;font-weight:bold;font-size:16px;">
                                    Xác thực tài khoản
                                </a>
                            </div>
                            <p style="color:#999;font-size:14px;line-height:1.6;margin:20px 0 0;">
                                Hoặc copy link sau vào trình duyệt:<br>
                                <a href="{$link}" style="color:#667eea;word-break:break-all;">{$link}</a>
                            </p>
                            <p style="color:#999;font-size:14px;margin:30px 0 0;padding-top:20px;border-top:1px solid #eee;">
                                <strong>⚠️ Lưu ý:</strong> Link này sẽ hết hạn sau 24 giờ.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f8f9fa;padding:20px;text-align:center;">
                            <p style="margin:0;color:#999;font-size:12px;">
                                © 2025 Transportation Renting
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
    
    private function getVerificationEmailPlainText($name, $link)
    {
        return "Xin chào {$name}!\n\nXác thực tài khoản: {$link}\n\n© 2025 Transportation Renting";
    }
    
    private function getPasswordResetTemplate($name, $link)
    {
        return "<h1>Đặt lại mật khẩu</h1><p>Xin chào {$name}, click link sau: {$link}</p>";
    }
    
    private function getPasswordResetPlainText($name, $link)
    {
        return "Xin chào {$name}!\n\nĐặt lại mật khẩu: {$link}";
    }
    
    private function getWelcomeEmailTemplate($name)
    {
        return "<h1>Chào mừng {$name}!</h1><p>Tài khoản đã được kích hoạt.</p>";
    }
    
    private function getWelcomeEmailPlainText($name)
    {
        return "Chào mừng {$name}!";
    }
    
    private function getBookingConfirmationTemplate($data)
    {
        $name = $data['customer_name'] ?? '';
        $bookingId = $data['booking_id'] ?? '';
        return "<h1>Xác nhận đặt xe</h1><p>Xin chào {$name}, đơn #{$bookingId} đã xác nhận.</p>";
    }
    
    private function getBookingConfirmationPlainText($data)
    {
        $name = $data['customer_name'] ?? '';
        $bookingId = $data['booking_id'] ?? '';
        return "Xin chào {$name}, đơn #{$bookingId} đã xác nhận.";
    }
}