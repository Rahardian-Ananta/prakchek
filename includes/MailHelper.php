<?php
// includes/MailHelper.php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailHelper {
    public static function sendResetEmail($toEmail, $toName, $resetLink) {
        $config = require __DIR__ . '/../config_mail.php';
        
        // Cek apakah key masih menggunakan asteriks placeholder
        $isDummyKey = (strpos($config['smtp_password'], '***') !== false || empty($config['smtp_password']));
        
        if ($isDummyKey) {
            self::logEmailToFile($toEmail, $toName, $resetLink, 'dummy_key');
            return true;
        }

        $mail = new PHPMailer(true);
        try {
            // SMTP Settings
            $mail->isSMTP();
            $mail->Host = $config['smtp_host'];
            $mail->SMTPAuth = $config['smtp_auth'];
            $mail->Username = $config['smtp_username'];
            $mail->Password = $config['smtp_password'];
            $mail->SMTPSecure = $config['smtp_secure'];
            $mail->Port = $config['smtp_port'];
            
            // Sender & Recipient
            $mail->setFrom($config['mail_from'], $config['mail_from_name']);
            $mail->addAddress($toEmail, $toName);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = '🔐 Prakchek LMS - Reset Password Request';
            $mail->Body = self::getResetEmailTemplate($toName, $resetLink);
            
            $mail->send();
            
            // Jika berhasil terkirim dan mode log aktif, catat ke log lokal untuk bantuan dev
            if ($config['use_fallback_log']) {
                self::logEmailToFile($toEmail, $toName, $resetLink, 'sent_smtp');
            }
            return true;
            
        } catch (Exception $e) {
            // Jika SMTP gagal, otomatis paksa catat ke log lokal sebagai penyelamat/backup
            self::logEmailToFile($toEmail, $toName, $resetLink, 'smtp_failed');
            return false;
        }
    }
    
    private static function logEmailToFile($toEmail, $toName, $resetLink, $status) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/mail_resets.txt';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [STATUS: {$status}] Reset Link for {$toName} ({$toEmail}): {$resetLink}\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        
        // Simpan info ke session untuk notifikasi di layar
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['dev_reset_link'] = $resetLink;
        $_SESSION['dev_reset_status'] = $status;
    }
    
    private static function getResetEmailTemplate($name, $resetLink) {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 500px; margin: auto; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white; padding: 24px; text-align: center;">
                <h2 style="margin: 0; font-size: 20px;">🔐 Prakchek LMS</h2>
                <p style="margin: 8px 0 0; opacity: 0.9;">Reset Password Request</p>
            </div>
            <div style="padding: 32px;">
                <p>Halo <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>Kami menerima permintaan untuk mereset kata sandi akun Prakchek LMS Anda. Silakan klik tombol di bawah ini untuk mereset kata sandi Anda:</p>
                
                <div style="text-align: center; margin: 32px 0;">
                    <a href="' . $resetLink . '" style="background: #4f46e5; color: white; padding: 14px 28px; border-radius: 8px; font-size: 16px; font-weight: bold; text-decoration: none; display: inline-block;">Reset Password Saya</a>
                </div>
                
                <p style="color: #6b7280; font-size: 14px;">Atau copy-paste tautan berikut ke browser Anda:</p>
                <p style="word-break: break-all; font-size: 13px; color: #4f46e5;"><a href="' . $resetLink . '">' . $resetLink . '</a></p>
                
                <p style="color: #6b7280; font-size: 13px; margin-top: 24px;">⏰ Tautan ini berlaku selama <strong>1 jam</strong>.</p>
                <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 24px 0;">
                <p style="color: #9ca3af; font-size: 12px;">
                    🔒 Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini atau hubungi admin kelas.
                </p>
            </div>
            <div style="background: #f9fafb; padding: 16px; text-align: center; color: #6b7280; font-size: 12px;">
                © ' . date('Y') . ' Prakchek LMS • Local Development
            </div>
        </div>';
    }
}
