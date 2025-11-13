<?php
// services/payment/classes/Mailer.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../../vendor/autoload.php';

class Mailer {
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->setup();
    }

    private function setup() {
        try {
            $this->mail->isSMTP();
            $this->mail->Host       = 'smtp.gmail.com';   // SMTP server
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = 'chungchi.chungduong@gmail.com'; // Gmail
            $this->mail->Password   = 'cqny sytp rlfj lztz';   // App password
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port       = 587;
            $this->mail->CharSet    = 'UTF-8';
        } catch (Exception $e) {
            throw new Exception("Mailer setup error: " . $e->getMessage());
        }
    }

    public function sendMail($to, $subject, $body) {
        try {
            $this->mail->setFrom('chungchi.chungduong@gmail.com', 'Payment System');
            $this->mail->addAddress($to);

            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;

            return $this->mail->send();
        } catch (Exception $e) {
            return "Mailer Error: " . $this->mail->ErrorInfo;
        }
    }
}
