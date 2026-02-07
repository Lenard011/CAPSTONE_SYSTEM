<?php
// mailer.php - REAL Gmail Configuration with Password Reset

// Include PHPMailer files
require_once __DIR__ . '/../../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private $mail;
    private $smtpConfig;

    public function __construct()
    {
        // REAL Gmail Configuration
        $this->smtpConfig = [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => 'dexter.balanza88@gmail.com', // YOUR GMAIL
            'password' => 'kersdyrvoririjiw', // YOUR GMAIL APP PASSWORD - 16 characters, no spaces
            'from_email' => 'noreply@paluan.gov.ph',
            'from_name' => 'Municipality of Paluan HRMO',
            'debug' => false, // Set to true for debugging
            'use_real_email' => true // Set to TRUE after adding App Password
        ];

        $this->initializeMailer();
    }

    private function initializeMailer()
    {
        if ($this->smtpConfig['use_real_email'] && !empty($this->smtpConfig['password'])) {
            try {
                $this->mail = new PHPMailer(true);

                // Server settings
                $this->mail->isSMTP();
                $this->mail->Host = $this->smtpConfig['host'];
                $this->mail->SMTPAuth = true;
                $this->mail->Username = $this->smtpConfig['username'];
                $this->mail->Password = $this->smtpConfig['password'];
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $this->mail->Port = $this->smtpConfig['port'];

                // Enable verbose debug output
                if ($this->smtpConfig['debug']) {
                    $this->mail->SMTPDebug = 2;
                    $this->mail->Debugoutput = function ($str, $level) {
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
    public function getPasswordStatus()
    {
        return empty($this->smtpConfig['password']) ? "❌ NOT SET" : "✅ SET";
    }

    public function isRealEmailEnabled()
    {
        return $this->smtpConfig['use_real_email'];
    }

    public function getUsername()
    {
        return $this->smtpConfig['username'];
    }

    public function getConfig()
    {
        return $this->smtpConfig;
    }

    // Debug information method
    public function getDebugInfo()
    {
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

    // ==================== PASSWORD RESET FUNCTIONALITY ====================
    // In your Mailer class, add this method:
    public function sendResetOTP($email, $otp, $name)
    {
        // Try to send real email if configured
        if ($this->smtpConfig['use_real_email'] && $this->mail && !empty($this->smtpConfig['password'])) {
            try {
                // Clear previous recipients
                $this->mail->clearAllRecipients();

                // Add recipient
                $this->mail->addAddress($email, $name);

                // Subject
                $this->mail->Subject = "Password Reset OTP - Municipality of Paluan HRMO";

                // HTML body
                $this->mail->Body = $this->createResetOTPTemplate($otp, $name);

                // Plain text alternative
                $this->mail->AltBody = $this->createResetOTPPlainText($otp, $name);

                // Send email
                if ($this->mail->send()) {
                    error_log("✅ Password reset OTP sent successfully to: $email");
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
                error_log("❌ Password reset OTP failed to $email: " . $e->getMessage());

                // Fallback to demo mode
                return $this->sendResetOTPDemoMode($email, $otp, $name, $e->getMessage());
            }
        }

        // If not configured for real email, use demo mode
        return $this->sendResetOTPDemoMode($email, $otp, $name);
    }

    private function sendResetOTPDemoMode($email, $otp, $name, $error = null)
    {
        // Show OTP on screen for development
        $message = 'Password reset OTP for <strong>' . $email . '</strong>: ';
        $message .= '<div style="font-size: 28px; font-weight: bold; color: #1e3a8a; padding: 15px; background: #f0f9ff; border-radius: 8px; margin: 10px 0;">' . $otp . '</div>';
        $message .= '<p>Name: ' . htmlspecialchars($name) . '</p>';

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

    private function createResetOTPTemplate($otp, $name)
    {
        return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Reset OTP</title>
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
                border-bottom: 4px solid #d4af37;
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
            .warning {
                background: #fff3cd;
                border: 1px solid #ffc107;
                padding: 15px;
                border-radius: 8px;
                margin: 20px 0;
                color: #856404;
            }
            .footer {
                text-align: center;
                padding: 20px;
                color: #64748b;
                font-size: 12px;
                border-top: 1px solid #e2e8f0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Municipality of Paluan HRMO</h2>
                <h3>Password Reset Verification</h3>
            </div>
            <div class="content">
                <p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>You have requested to reset your password. Use the OTP below to verify your identity:</p>
                
                <div class="otp-box">' . $otp . '</div>
                
                <div class="warning">
                    <p><strong>⚠️ This OTP expires in 10 minutes.</strong></p>
                    <p>If you didn\'t request this password reset, please ignore this email.</p>
                    <p>For security reasons, do not share this OTP with anyone.</p>
                </div>
                
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

    private function createResetOTPPlainText($otp, $name)
    {
        return "Municipality of Paluan HRMO\n" .
            "Password Reset Verification\n\n" .
            "Hello " . $name . ",\n\n" .
            "You have requested to reset your password. Use the OTP below to verify your identity:\n\n" .
            "OTP: " . $otp . "\n\n" .
            "⚠️ This OTP expires in 10 minutes.\n\n" .
            "If you didn't request this password reset, please ignore this email.\n" .
            "For security reasons, do not share this OTP with anyone.\n\n" .
            "Best regards,\n" .
            "Municipality of Paluan HRMO\n\n" .
            "This is an automated message. Please do not reply.";
    }

    public function sendPasswordReset($email, $name, $resetLink)
    {
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
                $this->mail->Subject = "Password Reset Request - Municipality of Paluan HRMO";

                // HTML body
                $this->mail->Body = $this->createPasswordResetTemplate($name, $resetLink);

                // Plain text alternative
                $this->mail->AltBody = $this->createPasswordResetPlainText($name, $resetLink);

                // Send email
                if ($this->mail->send()) {
                    error_log("✅ Password reset email sent successfully to: $email");
                    return [
                        'success' => true,
                        'message' => 'Password reset instructions have been sent to your email.',
                        'email_sent' => true,
                        'sent_to' => $email
                    ];
                } else {
                    throw new Exception('Failed to send email: ' . $this->mail->ErrorInfo);
                }

            } catch (Exception $e) {
                // Log the error
                error_log("❌ Password reset email failed to $email: " . $e->getMessage());

                // Fallback to demo mode
                return $this->sendPasswordResetDemoMode($email, $name, $resetLink, $e->getMessage());
            }
        }

        // If not configured for real email, use demo mode
        return $this->sendPasswordResetDemoMode($email, $name, $resetLink);
    }

    private function sendPasswordResetDemoMode($email, $name, $resetLink, $error = null)
    {
        // Show reset link on screen for development
        $message = 'Password reset link for <strong>' . $email . '</strong>: ';
        $message .= '<div style="font-size: 16px; padding: 15px; background: #f0f9ff; border-radius: 8px; margin: 10px 0; word-break: break-all;">';
        $message .= '<a href="' . htmlspecialchars($resetLink) . '" style="color: #1e3a8a;">' . htmlspecialchars($resetLink) . '</a>';
        $message .= '</div>';
        $message .= '<p>Name: ' . htmlspecialchars($name) . '</p>';

        if ($error) {
            $message .= '<div style="color: #ef4444; font-size: 12px; margin-top: 10px;">Error: ' . htmlspecialchars($error) . '</div>';
        }

        $message .= '<div style="color: #6b7280; font-size: 12px; margin-top: 10px;">To enable real email: Add Gmail App Password to mailer.php</div>';

        return [
            'success' => true,
            'demo_mode' => true,
            'demo_link' => $resetLink,
            'message' => $message,
            'email_sent' => false,
            'error' => $error
        ];
    }

    private function createPasswordResetTemplate($name, $resetLink)
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Password Reset</title>
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
                    border-bottom: 4px solid #d4af37;
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
                .reset-button {
                    display: inline-block;
                    background: linear-gradient(135deg, #1e3a8a, #1e293b);
                    color: white;
                    text-decoration: none;
                    padding: 15px 40px;
                    border-radius: 8px;
                    font-weight: bold;
                    font-size: 16px;
                    margin: 25px 0;
                    border: none;
                    box-shadow: 0 4px 15px rgba(30, 58, 138, 0.3);
                    transition: all 0.3s ease;
                }
                .reset-button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(30, 58, 138, 0.4);
                }
                .reset-link {
                    background: #f1f5f9;
                    padding: 20px;
                    border-radius: 8px;
                    margin: 20px 0;
                    font-family: monospace;
                    font-size: 14px;
                    word-break: break-all;
                    color: #1e293b;
                }
                .warning-box {
                    background: #fff3cd;
                    border: 1px solid #ffc107;
                    border-left: 4px solid #ffc107;
                    padding: 20px;
                    border-radius: 8px;
                    margin: 25px 0;
                }
                .warning-box h4 {
                    margin-top: 0;
                    color: #856404;
                }
                .footer {
                    text-align: center;
                    padding: 20px;
                    color: #64748b;
                    font-size: 12px;
                    border-top: 1px solid #e2e8f0;
                }
                .logo {
                    max-width: 80px;
                    margin-bottom: 20px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Municipality of Paluan</h2>
                    <h3>Human Resource Management Office</h3>
                </div>
                <div class="content">
                    <p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>
                    <p>You have requested to reset your password for the HRMO Admin Portal.</p>
                    
                    <div style="text-align: center;">
                        <a href="' . htmlspecialchars($resetLink) . '" class="reset-button">Reset Password</a>
                    </div>
                    
                    <p>Or copy and paste this link into your browser:</p>
                    <div class="reset-link">' . htmlspecialchars($resetLink) . '</div>
                    
                    <div class="warning-box">
                        <h4>⚠️ Important Security Notice</h4>
                        <ul style="margin: 10px 0; padding-left: 20px;">
                            <li>This password reset link will expire in <strong>1 hour</strong></li>
                            <li>If you did not request this password reset, please ignore this email</li>
                            <li>For security reasons, do not share this link with anyone</li>
                            <li>After resetting your password, this link will no longer work</li>
                        </ul>
                    </div>
                    
                    <p>If you\'re having trouble clicking the button, copy and paste the URL above into your web browser.</p>
                    
                    <p>Best regards,<br>
                    <strong>Municipality of Paluan HRMO Team</strong></p>
                </div>
                <div class="footer">
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>If you need assistance, please contact the system administrator.</p>
                    <p>© ' . date('Y') . ' Municipality of Paluan. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }

    private function createPasswordResetPlainText($name, $resetLink)
    {
        return "Municipality of Paluan\n" .
            "Human Resource Management Office\n\n" .
            "Password Reset Request\n" .
            "=====================\n\n" .
            "Hello " . $name . ",\n\n" .
            "You have requested to reset your password for the HRMO Admin Portal.\n\n" .
            "To reset your password, click the following link:\n" .
            $resetLink . "\n\n" .
            "If you cannot click the link, copy and paste it into your browser.\n\n" .
            "IMPORTANT SECURITY NOTICE:\n" .
            "- This password reset link will expire in 1 hour\n" .
            "- If you did not request this password reset, please ignore this email\n" .
            "- For security reasons, do not share this link with anyone\n" .
            "- After resetting your password, this link will no longer work\n\n" .
            "Best regards,\n" .
            "Municipality of Paluan HRMO Team\n\n" .
            "This is an automated message. Please do not reply to this email.\n" .
            "If you need assistance, please contact the system administrator.\n" .
            "© " . date('Y') . " Municipality of Paluan. All rights reserved.";
    }

    // ==================== OTP FUNCTIONALITY ====================

    public function sendOTP($email, $otp, $name)
    {
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

    private function sendOTPDemoMode($email, $otp, $name, $error = null)
    {
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

    public function sendMail($email, $subject, $message)
    {
        // For backward compatibility with createTestAccounts
        $result = $this->sendOTP($email, 'N/A', 'User');
        return $result['success'];
    }

    // ==================== GENERAL EMAIL FUNCTION ====================

    public function sendGeneralEmail($email, $name, $subject, $body)
    {
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
                $this->mail->Subject = $subject;

                // HTML body
                $this->mail->Body = $body;

                // Plain text alternative (strip tags from HTML)
                $this->mail->AltBody = strip_tags($body);

                // Send email
                if ($this->mail->send()) {
                    error_log("✅ General email sent successfully to: $email");
                    return [
                        'success' => true,
                        'message' => 'Email has been sent successfully.',
                        'email_sent' => true,
                        'sent_to' => $email
                    ];
                } else {
                    throw new Exception('Failed to send email: ' . $this->mail->ErrorInfo);
                }

            } catch (Exception $e) {
                // Log the error
                error_log("❌ General email failed to $email: " . $e->getMessage());

                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'email_sent' => false
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'Email not configured',
            'email_sent' => false
        ];
    }

    // ==================== TEST & CONFIGURATION ====================

    // Test SMTP connection
    public function testConnection()
    {
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
    public function updateConfig($config)
    {
        $this->smtpConfig = array_merge($this->smtpConfig, $config);
        $this->initializeMailer();
    }

    private function createEmailTemplate($otp, $name)
    {
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

    private function createPlainTextTemplate($otp, $name)
    {
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

// Function to send verification invitation with temporary credentials
function sendVerificationInvitationEmail($conn, $user_email, $user_name, $temp_password, $verification_link) {
    // Get login page URL (assuming login.php is in the same directory)
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $login_url = $base_url . "/CAPSTONE_SYSTEM/admin/php/login.php";
    
    $subject = "Your HRMS Account is Ready - Login Credentials";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                background-color: #f8fafc;
                margin: 0;
                padding: 0;
            }
            .container { 
                max-width: 650px; 
                margin: 0 auto; 
                background: white;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            }
            .header { 
                background: linear-gradient(135deg, #0235a2 0%, #1e3a8a 100%); 
                color: white; 
                padding: 35px 30px;
                text-align: center; 
                border-bottom: 5px solid #2c6bc4;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 700;
            }
            .header .subtitle {
                font-size: 16px;
                opacity: 0.9;
                margin-top: 10px;
            }
            .content { 
                padding: 40px 35px; 
                border: 1px solid #e5e7eb;
            }
            .greeting {
                font-size: 20px;
                color: #1e293b;
                margin-bottom: 25px;
                font-weight: 600;
            }
            .credentials-box { 
                background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); 
                border: 2px solid #2c6bc4; 
                padding: 30px; 
                margin: 25px 0; 
                border-radius: 12px;
                border-left: 5px solid #2c6bc4;
            }
            .credentials-box h3 {
                color: #1e40af;
                margin-top: 0;
                margin-bottom: 20px;
                font-size: 22px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .credentials-box h3 i {
                color: #2c6bc4;
            }
            .credential-item {
                margin-bottom: 18px;
                padding-bottom: 18px;
                border-bottom: 1px dashed #cbd5e1;
            }
            .credential-item:last-child {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }
            .credential-label {
                font-weight: 600;
                color: #475569;
                margin-bottom: 5px;
                font-size: 15px;
            }
            .credential-value {
                font-size: 18px;
                color: #1e293b;
                font-weight: 600;
                word-break: break-all;
            }
            .password-display {
                background: #1e293b;
                color: #ffffff;
                padding: 15px;
                border-radius: 8px;
                font-family: 'Courier New', monospace;
                font-size: 20px;
                letter-spacing: 2px;
                text-align: center;
                margin: 15px 0;
                border: 2px solid #2c6bc4;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            .highlight {
                background: #fff7ed;
                padding: 3px 8px;
                border-radius: 4px;
                font-weight: 600;
                color: #ea580c;
            }
            .steps-box {
                background: #f8fafc;
                border-radius: 10px;
                padding: 25px;
                margin: 30px 0;
                border: 1px solid #e2e8f0;
            }
            .steps-box h4 {
                color: #1e40af;
                margin-top: 0;
                margin-bottom: 20px;
                font-size: 18px;
            }
            .steps-list {
                counter-reset: step-counter;
                list-style: none;
                padding: 0;
            }
            .steps-list li {
                counter-increment: step-counter;
                margin-bottom: 20px;
                padding-left: 40px;
                position: relative;
                font-size: 15px;
                line-height: 1.5;
            }
            .steps-list li:before {
                content: counter(step-counter);
                background: #2c6bc4;
                color: white;
                width: 28px;
                height: 28px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                position: absolute;
                left: 0;
                top: 0;
                font-weight: bold;
                font-size: 14px;
            }
            .login-button-container {
                text-align: center;
                margin: 35px 0;
            }
            .login-button {
                display: inline-block;
                background: linear-gradient(135deg, #2c6bc4 0%, #1e4a8a 100%); 
                color: white; 
                padding: 18px 40px;
                text-decoration: none; 
                border-radius: 10px; 
                font-weight: 700;
                font-size: 18px;
                transition: all 0.3s ease;
                box-shadow: 0 8px 20px rgba(44, 107, 196, 0.3);
                border: none;
            }
            .login-button:hover {
                transform: translateY(-3px);
                box-shadow: 0 12px 25px rgba(44, 107, 196, 0.4);
                background: linear-gradient(135deg, #1e4a8a 0%, #2c6bc4 100%);
            }
            .warning-box { 
                background: linear-gradient(135deg, #fef3c7 0%, #fef9c3 100%); 
                border-left: 5px solid #f59e0b; 
                padding: 22px; 
                margin: 25px 0; 
                border-radius: 8px;
            }
            .warning-box h4 {
                color: #92400e;
                margin-top: 0;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .warning-box h4 i {
                color: #f59e0b;
            }
            .important-notes {
                background: #f1f5f9;
                border-radius: 10px;
                padding: 25px;
                margin: 30px 0;
            }
            .important-notes h4 {
                color: #1e40af;
                margin-top: 0;
                margin-bottom: 15px;
            }
            .notes-list {
                list-style: none;
                padding: 0;
            }
            .notes-list li {
                padding-left: 30px;
                margin-bottom: 12px;
                position: relative;
                font-size: 14.5px;
                color: #475569;
            }
            .notes-list li:before {
                content: '•';
                color: #2c6bc4;
                font-size: 20px;
                position: absolute;
                left: 10px;
                top: -2px;
            }
            .footer { 
                margin-top: 40px; 
                padding-top: 30px; 
                border-top: 1px solid #e5e7eb; 
                color: #64748b; 
                font-size: 14px;
                text-align: center;
            }
            .footer p {
                margin: 8px 0;
            }
            .footer strong {
                color: #1e293b;
            }
            .system-name {
                color: #2c6bc4;
                font-weight: 700;
            }
            .contact-info {
                background: #f8fafc;
                padding: 20px;
                border-radius: 8px;
                margin-top: 20px;
                font-size: 14px;
                color: #475569;
            }
            @media (max-width: 600px) {
                .container {
                    border-radius: 0;
                }
                .header {
                    padding: 25px 20px;
                }
                .content {
                    padding: 30px 20px;
                }
                .credentials-box {
                    padding: 20px;
                }
                .password-display {
                    font-size: 18px;
                    padding: 12px;
                }
                .login-button {
                    padding: 16px 30px;
                    font-size: 16px;
                    width: 100%;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>HR Management System Account</h1>
                <div class='subtitle'>Municipality of Paluan, Occidental Mindoro</div>
            </div>
            
            <div class='content'>
                <div class='greeting'>Hello <span class='highlight'>$user_name</span>,</div>
                
                <p>Welcome to the HR Management System of Paluan! Your employee account has been successfully created and is ready for use.</p>
                
                <div class='warning-box'>
                    <h4><i class='fas fa-shield-alt'></i> Important Security Notice</h4>
                    <p>You have been provided with temporary credentials. For security reasons, you <strong>must</strong> change your password immediately after your first login.</p>
                </div>
                
                <div class='credentials-box'>
                    <h3><i class='fas fa-key'></i> Your Login Credentials</h3>
                    
                    <div class='credential-item'>
                        <div class='credential-label'>Login Portal URL</div>
                        <div class='credential-value'>$login_url</div>
                    </div>
                    
                    <div class='credential-item'>
                        <div class='credential-label'>Username / Email Address</div>
                        <div class='credential-value'>$user_email</div>
                    </div>
                    
                    <div class='credential-item'>
                        <div class='credential-label'>Temporary Password</div>
                        <div class='password-display'>$temp_password</div>
                        <div style='text-align: center; font-size: 14px; color: #64748b; margin-top: 10px;'>
                            <i class='fas fa-clock'></i> Expires in 24 hours
                        </div>
                    </div>
                    
                    <div class='credential-item'>
                        <div class='credential-label'>Login Method</div>
                        <div class='credential-value'>Use your email address as username</div>
                    </div>
                </div>
                
                <div class='steps-box'>
                    <h4>How to Access Your Account:</h4>
                    <ol class='steps-list'>
                        <li><strong>Go to the login page:</strong> Visit $login_url or click the button below</li>
                        <li><strong>Enter your credentials:</strong> Use your email (<strong>$user_email</strong>) and the temporary password shown above</li>
                        <li><strong>Set your permanent password:</strong> You will be prompted to create a new secure password immediately</li>
                        <li><strong>Complete your profile:</strong> Review and update your personal information as needed</li>
                        <li><strong>Start using the system:</strong> Access all HR features including attendance, payroll, and documents</li>
                    </ol>
                </div>
                
                <div class='login-button-container'>
                    <a href='$login_url' class='login-button'>
                        <i class='fas fa-sign-in-alt'></i> Go to Login Page
                    </a>
                </div>
                
                <div class='important-notes'>
                    <h4><i class='fas fa-exclamation-circle'></i> Important Information</h4>
                    <ul class='notes-list'>
                        <li>This temporary password is valid for <strong>24 hours only</strong></li>
                        <li>You <strong>must change your password</strong> immediately on first login</li>
                        <li>Do <strong>not share your credentials</strong> with anyone</li>
                        <li>Choose a strong password with uppercase, lowercase, numbers, and special characters</li>
                        <li>If you experience any issues, contact your HR administrator immediately</li>
                        <li>This is an official government system - all activities are logged and monitored</li>
                    </ul>
                </div>
                
                <div class='contact-info'>
                    <p><strong>Need Help?</strong> Contact HR Department:</p>
                    <p>📞 Phone: (043) 123-4567 | 📧 Email: hrmo@paluan.gov.ph</p>
                    <p>🕒 Office Hours: Monday-Friday, 8:00 AM - 5:00 PM</p>
                </div>
                
                <div class='footer'>
                    <p>Best regards,</p>
                    <p><strong>Human Resource Management Office</strong><br>
                    <span class='system-name'>HR Management System</span><br>
                    Municipality of Paluan, Occidental Mindoro</p>
                    <p><small>This is an automated system message. Please do not reply to this email.</small></p>
                    <p><small>Government Property | Confidential Information</small></p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: HRMS Paluan <hrmo@paluan.gov.ph>" . "\r\n";
    $headers .= "Reply-To: HR Department <hrmo@paluan.gov.ph>" . "\r\n";
    $headers .= "X-Priority: 1 (Highest)" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Try to send email
    if (mail($user_email, $subject, $message, $headers)) {
        return true;
    } else {
        // Log error for debugging
        error_log("Failed to send email to: $user_email");
        return false;
    }
}

// Alternative version using PHPMailer (if available)
function sendVerificationEmailPHPMailer($user_email, $user_name, $temp_password, $login_url) {
    // Check if PHPMailer is available
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        require_once 'vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Set your SMTP server
            $mail->SMTPAuth = true;
            $mail->Username = 'hrmo@paluan.gov.ph'; // SMTP username
            $mail->Password = 'your-email-password'; // SMTP password
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Recipients
            $mail->setFrom('hrmo@paluan.gov.ph', 'HRMS Paluan');
            $mail->addAddress($user_email, $user_name);
            $mail->addReplyTo('hrmo@paluan.gov.ph', 'HR Department');
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Your HRMS Account is Ready - Login Credentials';
            $mail->Body = generateEmailHTML($user_name, $user_email, $temp_password, $login_url);
            $mail->AltBody = generateEmailText($user_name, $user_email, $temp_password, $login_url);
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            return false;
        }
    }
    
    return false;
}

// Generate plain text version of email
function generateEmailText($user_name, $user_email, $temp_password, $login_url) {
    return "
    HR MANAGEMENT SYSTEM - MUNICIPALITY OF PALUAN
    =============================================
    
    Hello $user_name,
    
    Welcome to the HR Management System of Paluan!
    
    YOUR LOGIN CREDENTIALS:
    =======================
    Login URL: $login_url
    Username/Email: $user_email
    Temporary Password: $temp_password
    
    IMPORTANT: This temporary password expires in 24 hours.
    
    HOW TO LOGIN:
    1. Go to: $login_url
    2. Enter your email: $user_email
    3. Enter temporary password: $temp_password
    4. You will be prompted to create a new permanent password
    
    SECURITY NOTES:
    - You MUST change your password on first login
    - Do NOT share your credentials with anyone
    - Choose a strong password (8+ characters, mixed case, numbers, symbols)
    
    For assistance, contact HR Department:
    Phone: (043) 123-4567
    Email: hrmo@paluan.gov.ph
    
    Best regards,
    Human Resource Management Office
    Municipality of Paluan, Occidental Mindoro
    
    This is an automated message. Please do not reply.
    ";
}

// Also update the user creation function to use this
function createUserWithTemporaryCredentials($conn, $user_data) {
    // Generate temporary password
    $temp_password = generateTemporaryPassword();
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
    
    // Set expiration for temp password (24 hours)
    $temp_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Insert user into database
    $sql = "INSERT INTO users (
        username, email, password_hash, first_name, last_name, full_name,
        role, account_status, is_active, is_verified, employee_id,
        employment_type, access_level, department, position,
        password_is_temporary, temporary_password_expiry, must_change_password,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE', 1, 1, ?, ?, ?, ?, ?, 1, ?, 1, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssssssssssss",
        $user_data['username'],
        $user_data['email'],
        $hashed_password,
        $user_data['first_name'],
        $user_data['last_name'],
        $user_data['full_name'],
        $user_data['role'],
        $user_data['employee_id'],
        $user_data['employment_type'],
        $user_data['access_level'],
        $user_data['department'],
        $user_data['position'],
        $temp_expiry
    );
    
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        
        // Send email with credentials
        $login_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/CAPSTONE_SYSTEM/admin/php/login.php";
        
        $email_sent = sendVerificationInvitationEmail($conn, $user_data['email'], $user_data['full_name'], $temp_password, $login_url);
        
        if ($email_sent) {
            return [
                'success' => true,
                'message' => 'User created successfully. Credentials sent via email.',
                'user_id' => $user_id
            ];
        } else {
            return [
                'success' => true,
                'message' => 'User created but email failed to send. Temporary password: ' . $temp_password,
                'user_id' => $user_id,
                'temp_password' => $temp_password
            ];
        }
    } else {
        return [
            'success' => false,
            'message' => 'Error creating user: ' . $conn->error
        ];
    }
}

// Function to generate temporary password
function generateTemporaryPassword($length = 12) {
    // Define character sets
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';
    $symbols = '!@#$%^&*';
    
    // Ensure at least one character from each set
    $password = '';
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $symbols[random_int(0, strlen($symbols) - 1)];
    
    // Fill remaining characters randomly
    $allChars = $lowercase . $uppercase . $numbers . $symbols;
    for ($i = 4; $i < $length; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }
    
    // Shuffle the password
    return str_shuffle($password);
}
?>