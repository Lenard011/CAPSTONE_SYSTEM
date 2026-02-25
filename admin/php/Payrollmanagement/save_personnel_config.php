<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Please login first']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid input data']);
    exit();
}

// Define config file path
$config_file = __DIR__ . '/config/payroll_personnel.json';
$config_dir = dirname($config_file);

// Create config directory if it doesn't exist
if (!is_dir($config_dir)) {
    if (!mkdir($config_dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create config directory']);
        exit();
    }
}

// Create backup directory
$backup_dir = __DIR__ . '/backups/payroll_config/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Create backup if file exists
if (file_exists($config_file)) {
    $backup_file = $backup_dir . 'payroll_personnel_' . date('Y-m-d_His') . '.json';
    copy($config_file, $backup_file);

    // Keep only last 10 backups
    $backups = glob($backup_dir . 'payroll_personnel_*.json');
    if (count($backups) > 10) {
        usort($backups, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        $to_delete = array_slice($backups, 0, count($backups) - 10);
        foreach ($to_delete as $file) {
            unlink($file);
        }
    }
}

// Save new configuration
$json_content = json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($config_file, $json_content, LOCK_EX)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to write config file']);
}
