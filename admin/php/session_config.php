<?php
// session_config.php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Use only if using HTTPS
ini_set('session.cookie_samesite', 'Strict');

// Set session lifetime (8 hours)
ini_set('session.gc_maxlifetime', 28800);
session_set_cookie_params(28800);
?>