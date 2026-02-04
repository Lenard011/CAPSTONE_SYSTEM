<?php
// config.php - Database configuration for HRMO Paluan
session_start();

// Database credentials
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'hrmo_paluan');
define('DB_USER', 'root');  // Change to 'hrmo_user' for production
define('DB_PASS', '');      // Set your XAMPP password

// Application settings
define('APP_NAME', 'HRMO Paluan Attendance System');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Asia/Manila');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection class
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->connection = new PDO($dsn, DB_USER, DB_PASS);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance->connection;
    }
}

// Helper function to get database connection
function getDB() {
    return Database::getInstance();
}

// Test connection
function testConnection() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT DATABASE() as db_name, NOW() as server_time");
        $result = $stmt->fetch();
        return [
            'success' => true,
            'database' => $result['db_name'],
            'server_time' => $result['server_time'],
            'message' => 'Connected to HRMO Paluan database successfully!'
        ];
    } catch(PDOException $e) {
        return [
            'success' => false,
            'message' => 'Connection failed: ' . $e->getMessage()
        ];
    }
}
?>