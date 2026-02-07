<?php
// session_test.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Session Test</h2>";

// Test 1: Simple session
session_name('HRMS_SESSION');
session_start();

if (isset($_GET['set'])) {
    $_SESSION['test'] = 'Hello World';
    $_SESSION['time'] = time();
    echo "<p>Session set! Go to <a href='session_test.php?check=1'>check session</a></p>";
    exit();
}

if (isset($_GET['check'])) {
    echo "<pre>Session Data: ";
    print_r($_SESSION);
    echo "</pre>";
    
    echo "<p>Cookie: " . (isset($_COOKIE['HRMS_SESSION']) ? $_COOKIE['HRMS_SESSION'] : 'NOT SET') . "</p>";
    
    if (isset($_SESSION['test'])) {
        echo "<p style='color:green;font-weight:bold;'>✓ SUCCESS: Session is working!</p>";
        echo "<p><a href='homepage.php'>Now try homepage</a></p>";
    } else {
        echo "<p style='color:red;font-weight:bold;'>✗ FAILED: Session NOT working</p>";
    }
    exit();
}

echo "<p><a href='session_test.php?set=1'>Step 1: Set Session</a></p>";
echo "<p><a href='homepage.php'>Try Homepage Directly</a></p>";
?>