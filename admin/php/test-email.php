<?php
session_start();
require_once 'mailer.php';

$mailer = new Mailer();

echo "<h2>Email Configuration Test</h2>";
echo "<div style='padding: 20px; background: #f0f9ff; border-radius: 10px; margin: 20px;'>";

// Get debug info
$debugInfo = $mailer->getDebugInfo();
echo "<h3>Current Configuration:</h3>";
echo "<pre>";
print_r($debugInfo);
echo "</pre>";

// Test connection
echo "<h3>SMTP Connection Test:</h3>";
$connectionTest = $mailer->testConnection();
if ($connectionTest['success']) {
    echo "<p style='color: green;'><strong>✅ " . $connectionTest['message'] . "</strong></p>";
} else {
    echo "<p style='color: red;'><strong>❌ " . $connectionTest['error'] . "</strong></p>";
}

// Test sending OTP email
echo "<h3>Test OTP Email:</h3>";
$testEmail = "dexter.balanza88@gmail.com";
$testName = "Dexter Balanza";
$testOTP = "123456";

$result = $mailer->sendOTP($testEmail, $testOTP, $testName);

echo "<pre>";
print_r($result);
echo "</pre>";

if (isset($result['demo_mode']) && $result['demo_mode']) {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>⚠️ Running in Demo Mode</h4>";
    echo "<p>Real emails are not being sent. Here's why:</p>";
    echo "<ul>";
    echo "<li><strong>Password set:</strong> " . ($debugInfo['password_set'] ? "✅ Yes" : "❌ No") . "</li>";
    echo "<li><strong>Use real email:</strong> " . ($debugInfo['use_real_email'] ? "✅ Yes" : "❌ No") . "</li>";
    echo "<li><strong>Mailer initialized:</strong> " . ($debugInfo['mailer_initialized'] ? "✅ Yes" : "❌ No") . "</li>";
    echo "</ul>";
    echo "</div>";
}

echo "</div>";

// Check Gmail requirements
echo "<h3>Gmail Requirements Checklist:</h3>";
echo "<ol>";
echo "<li>✅ Use Gmail account: punzalanmarkjhon8@gmail.com</li>";
echo "<li>✅ Enable 2-factor authentication in Gmail account</li>";
echo "<li>❓ Generate App Password in Google Account</li>";
echo "<li>❓ Use App Password (16 characters) not regular password</li>";
echo "<li>❓ Allow less secure apps: Usually disabled now, use App Password instead</li>";
echo "</ol>";

echo "<h3>Steps to Fix:</h3>";
echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px;'>";
echo "<h4>1. Generate Gmail App Password:</h4>";
echo "<ul>";
echo "<li>Go to <a href='https://myaccount.google.com/security' target='_blank'>Google Account Security</a></li>";
echo "<li>Enable 2-Step Verification if not already enabled</li>";
echo "<li>Under 'Signing in to Google', click 'App passwords'</li>";
echo "<li>Select 'Mail' as the app</li>";
echo "<li>Select 'Other' as the device and name it 'Paluan HRMO'</li>";
echo "<li>Copy the 16-character password (no spaces)</li>";
echo "</ul>";

echo "<h4>2. Update mailer.php:</h4>";
echo "<p>Replace the current password in mailer.php with your new 16-character App Password:</p>";
echo "<pre style='background: white; padding: 10px;'>";
echo "'password' => 'your-16-character-app-password-here', // YOUR GMAIL APP PASSWORD";
echo "</pre>";

echo "<h4>3. Test Again:</h4>";
echo "<p>Refresh this page after updating the password.</p>";
echo "</div>";
?>