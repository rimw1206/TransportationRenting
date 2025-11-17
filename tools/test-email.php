<?php
/**
 * ============================================
 * test-email.php
 * Script để test email configuration
 * ============================================
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../env-bootstrap.php';
require_once __DIR__ . '/../shared/classes/EmailService.php';

echo "📧 Email Configuration Test\n";
echo "================================\n\n";

// Show current configuration
echo "📋 Current Settings:\n";
echo "   MAIL_HOST: " . (getenv('MAIL_HOST') ?: 'not set') . "\n";
echo "   MAIL_PORT: " . (getenv('MAIL_PORT') ?: 'not set') . "\n";
echo "   MAIL_USERNAME: " . (getenv('MAIL_USERNAME') ?: 'not set') . "\n";
echo "   MAIL_FROM_ADDRESS: " . (getenv('MAIL_FROM_ADDRESS') ?: 'not set') . "\n";
echo "   APP_URL: " . (getenv('APP_URL') ?: 'not set') . "\n\n";

// Check if credentials are set
if (empty(getenv('MAIL_USERNAME')) || empty(getenv('MAIL_PASSWORD'))) {
    echo "❌ ERROR: Email credentials not configured!\n\n";
    echo "📝 Setup instructions:\n";
    echo "1. Edit .env file\n";
    echo "2. Set MAIL_USERNAME and MAIL_PASSWORD\n";
    echo "3. For Gmail: Use App Password (not your regular password)\n\n";
    echo "Gmail App Password guide:\n";
    echo "→ https://myaccount.google.com/apppasswords\n\n";
    exit(1);
}

try {
    $emailService = new EmailService();
    
    echo "🔌 Testing SMTP connection...\n";
    
    // Get test email from command line or use sender email
    $testEmail = $argv[1] ?? getenv('MAIL_USERNAME');
    
    echo "   Sending test email to: {$testEmail}\n\n";
    
    $result = $emailService->testConnection($testEmail);
    
    if ($result['success']) {
        echo "✅ SUCCESS: {$result['message']}\n";
        echo "   Check your inbox: {$testEmail}\n\n";
        
        // Test verification email
        echo "📧 Testing verification email template...\n";
        $testToken = bin2hex(random_bytes(32));
        $verificationSent = $emailService->sendVerificationEmail($testEmail, $testToken, 'Test User');
        
        if ($verificationSent) {
            echo "✅ Verification email sent successfully!\n";
            echo "   Token: {$testToken}\n\n";
        } else {
            echo "⚠️  Verification email failed\n\n";
        }
        
        echo "🎉 Email service is working correctly!\n";
    } else {
        echo "❌ FAILED: {$result['message']}\n\n";
        
        echo "🔧 Troubleshooting:\n";
        echo "1. Check MAIL_USERNAME and MAIL_PASSWORD in .env\n";
        echo "2. For Gmail: Enable 'Less secure app access' OR use App Password\n";
        echo "3. Check firewall/antivirus blocking SMTP port\n";
        echo "4. Try different SMTP port (465 for SSL, 587 for TLS)\n";
        echo "5. Enable MAIL_DEBUG=true in .env for detailed logs\n";
    }
    
} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n================================\n";
echo "Test completed: " . date('Y-m-d H:i:s') . "\n";