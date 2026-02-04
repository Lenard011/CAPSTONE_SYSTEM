<?php
// mailer.php - REAL Gmail Configuration

// Include PHPMailer files
require_once __DIR__ . '/../../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;
    private $smtpConfig;
    
    public function __construct() {
        // REAL Gmail Configuration
        $this->smtpConfig = [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => 'punzalanmarkjhon8@gmail.com', // YOUR GMAIL
            'password' => 'Coddex123', // YOUR GMAIL APP PASSWORD - 16 characters, no spaces
            'from_email' => 'noreply@paluan.gov.ph',
            'from_name' => 'Municipality of Paluan HRMO',
            'debug' => false, // Set to true for debugging
            'use_real_email' => true // Set to TRUE after adding App Password
        ];
        
        $this->initializeMailer();
    }
    
    private function initializeMailer() {
        if ($this->smtpConfig['use_real_email'] && !empty($this->smtpConfig['password'])) {
            try {
                $this->mail = new PHPMailer(true);
                
                // Server settings
                $this->mail->isSMTP();
                $this->mail->Host       = $this->smtpConfig['host'];
                $this->mail->SMTPAuth   = true;
                $this->mail->Username   = $this->smtpConfig['username'];
                $this->mail->Password   = $this->smtpConfig['password'];
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $this->mail->Port       = $this->smtpConfig['port'];
                
                // Enable verbose debug output
                if ($this->smtpConfig['debug']) {
                    $this->mail->SMTPDebug = 2;
                    $this->mail->Debugoutput = function($str, $level) {
                        error_log("SMTP Debug: $str");
                    };
                }
                
                // Sender
                $this->mail->setFrom($this->smtpConfig['from_email'], $this->smtpConfig['from_name']);
                $this->mail->addReplyTo($this->smtpConfig['from_email'], $this->smtpConfig['from_name']);
                
                $this->mail->isHTML(true);
                $this->mail->CharSet = 'UTF-8';
                
                // Test SMTP connection
                if (!$this->mail->smtpConnect()) {
                    throw new Exception("Failed to connect to SMTP server");
                }
                $this->mail->smtpClose();
                
            } catch (Exception $e) {
                error_log("Mailer initialization error: " . $e->getMessage());
                $this->mail = null;
            }
        }
    }
    
    // GETTER METHODS for test_email.php
    public function getPasswordStatus() {
        return empty($this->smtpConfig['password']) ? "❌ NOT SET" : "✅ SET";
    }
    
    public function isRealEmailEnabled() {
        return $this->smtpConfig['use_real_email'];
    }
    
    public function getUsername() {
        return $this->smtpConfig['username'];
    }
    
    public function getConfig() {
        return $this->smtpConfig;
    }
    
    // Debug information method
    public function getDebugInfo() {
        return [
            'mailer_initialized' => !is_null($this->mail),
            'password_set' => !empty($this->smtpConfig['password']),
            'use_real_email' => $this->smtpConfig['use_real_email'],
            'config' => [
                'host' => $this->smtpConfig['host'],
                'port' => $this->smtpConfig['port'],
                'username' => $this->smtpConfig['username'],
                'password_length' => strlen($this->smtpConfig['password']),
                'from_email' => $this->smtpConfig['from_email']
            ]
        ];
    }
    
    public function sendOTP($email, $otp, $name) {
        // Try to send real email if configured
        if ($this->smtpConfig['use_real_email'] && $this->mail && !empty($this->smtpConfig['password'])) {
            try {
                // Clear previous recipients
                $this->mail->clearAddresses();
                $this->mail->clearCCs();
                $this->mail->clearBCCs();
                $this->mail->clearReplyTos();
                $this->mail->clearAllRecipients();
                $this->mail->clearAttachments();
                $this->mail->clearCustomHeaders();
                
                // Add recipient
                $this->mail->addAddress($email, $name);
                
                // Subject
                $this->mail->Subject = "Your OTP for Admin Portal Login - Municipality of Paluan HRMO";
                
                // HTML body
                $this->mail->Body = $this->createEmailTemplate($otp, $name);
                
                // Plain text alternative
                $this->mail->AltBody = $this->createPlainTextTemplate($otp, $name);
                
                // Send email
                if ($this->mail->send()) {
                    error_log("✅ Email sent successfully to: $email");
                    return [
                        'success' => true, 
                        'message' => 'OTP has been sent to your email address.',
                        'email_sent' => true,
                        'sent_to' => $email
                    ];
                } else {
                    throw new Exception('Failed to send email: ' . $this->mail->ErrorInfo);
                }
                
            } catch (Exception $e) {
                // Log the error
                error_log("❌ Email sending failed to $email: " . $e->getMessage());
                
                // Fallback to demo mode
                return $this->sendOTPDemoMode($email, $otp, $name, $e->getMessage());
            }
        }
        
        // If not configured for real email, use demo mode
        return $this->sendOTPDemoMode($email, $otp, $name);
    }
    
    private function sendOTPDemoMode($email, $otp, $name, $error = null) {
        // Show OTP on screen for development
        $message = 'OTP for <strong>' . $email . '</strong>: ';
        $message .= '<div style="font-size: 28px; font-weight: bold; color: #1e3a8a; padding: 15px; background: #f0f9ff; border-radius: 8px; margin: 10px 0;">' . $otp . '</div>';
        
        if ($error) {
            $message .= '<div style="color: #ef4444; font-size: 12px; margin-top: 10px;">Error: ' . htmlspecialchars($error) . '</div>';
        }
        
        $message .= '<div style="color: #6b7280; font-size: 12px; margin-top: 10px;">To enable real email: Add Gmail App Password to mailer.php</div>';
        
        return [
            'success' => true,
            'demo_mode' => true,
            'demo_otp' => $otp,
            'message' => $message,
            'email_sent' => false,
            'error' => $error
        ];
    }
    
    public function sendMail($email, $subject, $message) {
        // For backward compatibility with createTestAccounts
        $result = $this->sendOTP($email, 'N/A', 'User');
        return $result['success'];
    }
    
    // Test SMTP connection
    public function testConnection() {
        if (!$this->mail) {
            return ['success' => false, 'error' => 'Mailer not initialized. Check configuration.'];
        }
        
        try {
            $this->mail->smtpConnect();
            $this->mail->smtpClose();
            return ['success' => true, 'message' => '✅ SMTP connection successful!'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => '❌ SMTP connection failed: ' . $e->getMessage()];
        }
    }
    
    // Update configuration
    public function updateConfig($config) {
        $this->smtpConfig = array_merge($this->smtpConfig, $config);
        $this->initializeMailer();
    }
    
    private function createEmailTemplate($otp, $name) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>OTP Verification</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    background-color: #f8fafc;
                    margin: 0;
                    padding: 20px;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                }
                .header {
                    background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
                    color: white;
                    padding: 30px 20px;
                    text-align: center;
                }
                .header h2 {
                    margin: 0;
                    font-size: 24px;
                }
                .header h3 {
                    margin: 10px 0 0;
                    font-size: 18px;
                    font-weight: 400;
                }
                .content {
                    padding: 40px;
                }
                .otp-box {
                    background: #3b82f6;
                    color: white;
                    font-size: 36px;
                    font-weight: bold;
                    text-align: center;
                    padding: 25px;
                    margin: 30px 0;
                    letter-spacing: 15px;
                    border-radius: 10px;
                    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
                }
                .footer {
                    text-align: center;
                    padding: 20px;
                    color: #64748b;
                    font-size: 12px;
                    border-top: 1px solid #e2e8f0;
                }
                .note {
                    background: #fef3c7;
                    padding: 15px;
                    border-radius: 8px;
                    margin: 20px 0;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Municipality of Paluan HRMO</h2>
                    <h3>Admin Portal Login Verification</h3>
                </div>
                <div class="content">
                    <p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>
                    <p>You have requested to log in to the Admin Portal. Use the OTP below:</p>
                    
                    <div class="otp-box">' . $otp . '</div>
                    
                    <div class="note">
                        <strong>⚠️ This OTP expires in 5 minutes.</strong>
                    </div>
                    
                    <p>If you didn\'t request this, please ignore this email.</p>
                    <p>For security reasons, do not share this OTP with anyone.</p>
                    
                    <p>Best regards,<br>
                    <strong>Municipality of Paluan HRMO</strong></p>
                </div>
                <div class="footer">
                    <p>This is an automated message. Please do not reply.</p>
                    <p>© ' . date('Y') . ' Municipality of Paluan HRMO. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }
    
    private function createPlainTextTemplate($otp, $name) {
        return "Municipality of Paluan HRMO\n" .
               "Admin Portal Login Verification\n\n" .
               "Hello " . $name . ",\n\n" .
               "You have requested to log in to the Admin Portal. Use the OTP below:\n\n" .
               "OTP: " . $otp . "\n\n" .
               "⚠️ This OTP expires in 5 minutes.\n\n" .
               "If you didn't request this, please ignore this email.\n" .
               "For security reasons, do not share this OTP with anyone.\n\n" .
               "Best regards,\n" .
               "Municipality of Paluan HRMO\n\n" .
               "This is an automated message. Please do not reply.";
    }
}
?>