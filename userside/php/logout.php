<?php
// logout.php - COMPLETE LOGOUT SCRIPT
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configure session exactly like login.php
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 0);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name('HRMS_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Log the logout action
if (isset($_SESSION['username'])) {
    error_log("User logout: " . $_SESSION['username']);
}

// Clear all session variables
$_SESSION = [];

// If it's desired to kill the session, also delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
    
    // Also clear the remember_me cookie
    setcookie('remember_me', '', time() - 3600, '/', $_SERVER['HTTP_HOST'] ?? '', true, true);
}

// Finally, destroy the session
session_destroy();

// Redirect to login page with success message
header('Location: login.php?logout=success');
exit();
?>