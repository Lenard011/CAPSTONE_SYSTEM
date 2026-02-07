<?php
// session_cleanup.php - Force clear all sessions and cookies
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Session Cleanup Utility</h1>";

// Clear all cookies
foreach ($_COOKIE as $name => $value) {
    setcookie($name, '', time() - 3600, '/');
    echo "Cleared cookie: $name<br>";
}

// Clear all sessions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

if (session_destroy()) {
    echo "Session destroyed<br>";
}

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

echo "<h3>All sessions and cookies cleared.</h3>";
echo '<a href="login.php">Go to Login</a><br>';
echo '<a href="homepage.php">Try Homepage</a>';
?>