<?php
session_start();
require_once '../conn.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    die('Unauthorized');
}

$filename = basename($_GET['file']);
$filepath = '../backups/' . $filename;

if (file_exists($filepath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
} else {
    die('File not found');
}
?>