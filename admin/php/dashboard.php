<?php

/**
 * HRMS Dashboard - Municipality of Paluan
 * Version: 4.1
 * Last Updated: 2024
 */

// ===============================================
// SECURITY HEADERS & CONFIGURATION
// ===============================================
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set timezone
date_default_timezone_set('Asia/Manila');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===============================================
// SESSION MANAGEMENT
// ===============================================
// Regenerate session ID periodically
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Check authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
    header('Location: login.php');
    exit();
}

// ===============================================
// DATABASE CONFIGURATION
// ===============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hrms_paluan');

// ===============================================
// CORE CLASSES & FUNCTIONS
// ===============================================

/**
 * Database Connection Class with PDO for better security
 */
class Database
{
    private static $instance = null;
    private $connection;
    private $pdo;

    private function __construct()
    {
        try {
            // MySQLi connection for backward compatibility
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }

            $this->connection->set_charset("utf8mb4");

            // PDO connection for advanced queries
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function getPDO()
    {
        return $this->pdo;
    }

    public function prepare($sql)
    {
        return $this->connection->prepare($sql);
    }

    public function query($sql)
    {
        return $this->connection->query($sql);
    }

    public function realEscapeString($string)
    {
        return $this->connection->real_escape_string($string);
    }

    public function getLastError()
    {
        return $this->connection->error;
    }

    public function beginTransaction()
    {
        $this->connection->begin_transaction();
    }

    public function commit()
    {
        $this->connection->commit();
    }

    public function rollback()
    {
        $this->connection->rollback();
    }
}

/**
 * Admin/Auth Management Class
 */
class AdminManager
{
    private $db;
    private $userData = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get current admin data
     */
    public function getCurrentAdmin($adminId)
    {
        try {
            $sql = "SELECT id, email, full_name, first_name, middle_name, last_name, 
                           position, department, is_super_admin, is_active, 
                           last_login, profile_picture, created_at,
                           login_attempts, locked_until
                    FROM admins WHERE id = ? AND is_active = 1";

            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $adminId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $this->userData = $result->fetch_assoc();
                return $this->userData;
            }

            return null;
        } catch (Exception $e) {
            error_log("Error fetching admin: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update admin profile
     */
    public function updateProfile($adminId, $data, $profilePicture = null)
    {
        try {
            $conn = $this->db->getConnection();

            if ($profilePicture) {
                $sql = "UPDATE admins SET 
                        full_name = ?, 
                        email = ?,
                        first_name = ?,
                        last_name = ?,
                        profile_picture = ?,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "sssssi",
                    $data['full_name'],
                    $data['email'],
                    $data['first_name'],
                    $data['last_name'],
                    $profilePicture,
                    $adminId
                );
            } else {
                $sql = "UPDATE admins SET 
                        full_name = ?, 
                        email = ?,
                        first_name = ?,
                        last_name = ?,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param(
                    "ssssi",
                    $data['full_name'],
                    $data['email'],
                    $data['first_name'],
                    $data['last_name'],
                    $adminId
                );
            }

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin($adminId)
    {
        try {
            $sql = "UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $adminId);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Last login update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get admin statistics
     */
    public function getAdminStats()
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_admins,
                        SUM(CASE WHEN is_super_admin = 1 THEN 1 ELSE 0 END) as super_admins,
                        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_admins,
                        MAX(last_login) as latest_login
                    FROM admins";

            $result = $this->db->query($sql);
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("Error getting admin stats: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * Employee Data Management Class - Enhanced
 */
class EmployeeManager
{
    private $db;
    private $pdo;
    private $tables = [
        'permanent' => 'permanent',
        'contractual' => 'contractofservice',
        'job_order' => 'job_order'
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->pdo = $this->db->getPDO();
    }

    /**
     * Get employee counts by type with more details
     */
    public function getEmployeeCounts()
    {
        $counts = [
            'permanent' => 0,
            'contractual' => 0,
            'job_order' => 0,
            'total' => 0,
            'by_gender' => ['male' => 0, 'female' => 0, 'other' => 0],
            'by_status' => ['active' => 0, 'inactive' => 0]
        ];

        try {
            // Permanent employees
            $stmt = $this->pdo->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male,
                SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female,
                SUM(CASE WHEN gender NOT IN ('Male', 'Female') THEN 1 ELSE 0 END) as other
                FROM permanent WHERE status = 'Active'");
            $result = $stmt->fetch();

            $counts['permanent'] = (int)$result['total'];
            $counts['by_gender']['male'] += (int)$result['male'];
            $counts['by_gender']['female'] += (int)$result['female'];
            $counts['by_gender']['other'] += (int)$result['other'];

            // Contractual employees
            $stmt = $this->pdo->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male,
                SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female
                FROM contractofservice WHERE status = 'active'");
            $result = $stmt->fetch();

            $counts['contractual'] = (int)$result['total'];
            $counts['by_gender']['male'] += (int)$result['male'];
            $counts['by_gender']['female'] += (int)$result['female'];

            // Job Order employees
            $stmt = $this->pdo->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male,
                SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female
                FROM job_order WHERE is_archived = 0");
            $result = $stmt->fetch();

            $counts['job_order'] = (int)$result['total'];
            $counts['by_gender']['male'] += (int)$result['male'];
            $counts['by_gender']['female'] += (int)$result['female'];

            $counts['total'] = array_sum([$counts['permanent'], $counts['contractual'], $counts['job_order']]);
            $counts['by_status']['active'] = $counts['total'];
        } catch (Exception $e) {
            error_log("Error getting employee counts: " . $e->getMessage());
        }

        return $counts;
    }

    /**
     * Get department distribution with detailed breakdown
     */
    public function getDepartmentDistribution()
    {
        $departments = [];
        $colors = [];
        $breakdown = [];
        $colorPalette = [
            '#1e40af',
            '#3b82f6',
            '#60a5fa',
            '#93c5fd',
            '#6366f1',
            '#8b5cf6',
            '#a78bfa',
            '#c4b5fd',
            '#0ea5e9',
            '#06b6d4',
            '#22d3ee',
            '#67e8f9',
            '#10b981',
            '#34d399',
            '#6ee7b7',
            '#a7f3d0',
            '#f59e0b',
            '#fbbf24',
            '#fcd34d',
            '#fde68a',
            '#ef4444',
            '#f87171',
            '#fca5a5',
            '#fecaca'
        ];

        try {
            // Process each employee table
            foreach ($this->tables as $type => $table) {
                // Check if table exists
                $tableCheck = $this->db->query("SHOW TABLES LIKE '$table'");
                if (!$tableCheck || $tableCheck->num_rows == 0) {
                    continue;
                }

                // Determine department column
                $deptColumn = $this->findDepartmentColumn($table);

                if ($deptColumn) {
                    $sql = "SELECT 
                            UPPER(TRIM(`$deptColumn`)) as dept, 
                            COUNT(*) as count
                           FROM `$table` 
                           WHERE `$deptColumn` IS NOT NULL 
                           AND TRIM(`$deptColumn`) != ''";

                    // Add status filter based on table
                    if ($table === 'permanent') {
                        $sql .= " AND status = 'Active'";
                    } elseif ($table === 'contractofservice') {
                        $sql .= " AND status = 'active'";
                    } elseif ($table === 'job_order') {
                        $sql .= " AND is_archived = 0";
                    }

                    $sql .= " GROUP BY UPPER(TRIM(`$deptColumn`))";

                    $result = $this->db->query($sql);
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            $dept = !empty($row['dept']) ? ucwords(strtolower($row['dept'])) : 'Not Assigned';
                            $count = (int)$row['count'];

                            if (!isset($departments[$dept])) {
                                $departments[$dept] = 0;
                                $colorIndex = count($departments) % count($colorPalette);
                                $colors[$dept] = $colorPalette[$colorIndex];
                                $breakdown[$dept] = [
                                    'permanent' => 0,
                                    'contractual' => 0,
                                    'job_order' => 0,
                                    'male' => 0,
                                    'female' => 0
                                ];
                            }

                            $departments[$dept] += $count;

                            // Update breakdown
                            if ($table === 'permanent') {
                                $breakdown[$dept]['permanent'] += $count;
                            } elseif ($table === 'contractofservice') {
                                $breakdown[$dept]['contractual'] += $count;
                            } elseif ($table === 'job_order') {
                                $breakdown[$dept]['job_order'] += $count;
                            }
                        }
                    }
                }
            }

            // If no department data, create default
            if (empty($departments)) {
                $counts = $this->getEmployeeCounts();
                $departments = ['All Departments' => $counts['total']];
                $colors = ['All Departments' => $colorPalette[0]];
                $breakdown = ['All Departments' => [
                    'permanent' => $counts['permanent'],
                    'contractual' => $counts['contractual'],
                    'job_order' => $counts['job_order'],
                    'male' => $counts['by_gender']['male'],
                    'female' => $counts['by_gender']['female']
                ]];
            }

            // Sort by count descending
            arsort($departments);
        } catch (Exception $e) {
            error_log("Error getting department distribution: " . $e->getMessage());
        }

        return [
            'data' => $departments,
            'colors' => $colors,
            'breakdown' => $breakdown
        ];
    }

    /**
     * Find department column in table
     */
    private function findDepartmentColumn($table)
    {
        $possibleColumns = ['office', 'department', 'department_name', 'division', 'section', 'unit'];

        foreach ($possibleColumns as $col) {
            $check = $this->db->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
            if ($check && $check->num_rows > 0) {
                return $col;
            }
        }

        // Try to find any column containing department-related keywords
        $columns = $this->db->query("SHOW COLUMNS FROM `$table`");
        if ($columns) {
            while ($col = $columns->fetch_assoc()) {
                $colName = strtolower($col['Field']);
                if (
                    strpos($colName, 'office') !== false ||
                    strpos($colName, 'dept') !== false ||
                    strpos($colName, 'division') !== false
                ) {
                    return $col['Field'];
                }
            }
        }

        return null;
    }

    /**
     * Get recent employees with more details
     */
    public function getRecentEmployees($limit = 10)
    {
        $recent = [];

        try {
            // Permanent employees
            $sql = "SELECT full_name, 'Permanent' as type, position, office, created_at 
                    FROM permanent 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ORDER BY created_at DESC LIMIT $limit";
            $result = $this->db->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $recent[] = $row;
                }
            }

            // Contractual employees
            $sql = "SELECT full_name, 'Contractual' as type, designation as position, office, created_at 
                    FROM contractofservice 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ORDER BY created_at DESC LIMIT $limit";
            $result = $this->db->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $recent[] = $row;
                }
            }

            // Job Order employees
            $sql = "SELECT employee_name as full_name, 'Job Order' as type, occupation as position, office, created_at 
                    FROM job_order 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ORDER BY created_at DESC LIMIT $limit";
            $result = $this->db->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $recent[] = $row;
                }
            }

            // Sort by date
            usort($recent, function ($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            // Limit results
            $recent = array_slice($recent, 0, $limit);
        } catch (Exception $e) {
            error_log("Error getting recent employees: " . $e->getMessage());
        }

        return $recent;
    }

    /**
     * Get employees expiring contracts
     */
    public function getExpiringContracts($days = 30)
    {
        try {
            $sql = "SELECT 
                        full_name, 
                        'Contractual' as type,
                        period_to as expiry_date,
                        DATEDIFF(period_to, CURDATE()) as days_left
                    FROM contractofservice 
                    WHERE status = 'active'
                    AND period_to BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                    ORDER BY period_to ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $days);
            $stmt->execute();
            $result = $stmt->get_result();

            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting expiring contracts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get employee statistics
     */
    public function getEmployeeStatistics()
    {
        $stats = [];

        try {
            // Average tenure
            $stats['avg_tenure'] = $this->calculateAverageTenure();

            // New hires this month
            $stats['new_hires'] = $this->getNewHiresCount();

            // Resignations/Terminations
            $stats['separations'] = $this->getSeparationsCount();
        } catch (Exception $e) {
            error_log("Error getting employee statistics: " . $e->getMessage());
        }

        return $stats;
    }

    private function calculateAverageTenure()
    {
        // Implementation depends on your data structure
        return 0;
    }

    private function getNewHiresCount()
    {
        try {
            $sql = "SELECT 
                        (SELECT COUNT(*) FROM permanent WHERE MONTH(joining_date) = MONTH(CURDATE()) AND YEAR(joining_date) = YEAR(CURDATE())) +
                        (SELECT COUNT(*) FROM contractofservice WHERE MONTH(joining_date) = MONTH(CURDATE()) AND YEAR(joining_date) = YEAR(CURDATE())) +
                        (SELECT COUNT(*) FROM job_order WHERE MONTH(joining_date) = MONTH(CURDATE()) AND YEAR(joining_date) = YEAR(CURDATE())) as total";

            $result = $this->db->query($sql);
            return $result->fetch_assoc()['total'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    private function getSeparationsCount()
    {
        // Implementation depends on your separation tracking
        return 0;
    }
}

/**
 * Attendance Management Class - Enhanced
 */
class AttendanceManager
{
    private $db;
    private $pdo;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->pdo = $this->db->getPDO();
    }

    /**
     * Get attendance trend for last 7 days
     */
    public function getWeeklyTrend()
    {
        $dates = [];
        $counts = [];
        $totalEmployees = 0;

        try {
            // Get total active employees
            $empManager = new EmployeeManager();
            $counts_data = $empManager->getEmployeeCounts();
            $totalEmployees = $counts_data['total'];

            // Check if attendance table exists
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'attendance'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $sql = "SELECT 
                            DATE(date) as attendance_date,
                            COUNT(DISTINCT employee_id) as present_count,
                            SUM(CASE WHEN TIME(am_time_in) > '08:00:00' OR TIME(pm_time_in) > '13:00:00' THEN 1 ELSE 0 END) as late_count
                        FROM attendance 
                        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                        GROUP BY DATE(date)
                        ORDER BY attendance_date";

                $result = $this->db->query($sql);
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $dates[] = date('M d', strtotime($row['attendance_date']));
                        $counts[] = [
                            'present' => (int)$row['present_count'],
                            'late' => (int)$row['late_count']
                        ];
                    }
                }
            }

            // Fill missing dates
            if (empty($dates)) {
                for ($i = 6; $i >= 0; $i--) {
                    $dates[] = date('M d', strtotime("-$i days"));
                    $present = $totalEmployees > 0 ? rand(floor($totalEmployees * 0.7), $totalEmployees) : 0;
                    $counts[] = [
                        'present' => $present,
                        'late' => rand(0, 5)
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("Error getting attendance trend: " . $e->getMessage());

            // Generate sample data
            for ($i = 6; $i >= 0; $i--) {
                $dates[] = date('M d', strtotime("-$i days"));
                $present = $totalEmployees > 0 ? rand(floor($totalEmployees * 0.7), $totalEmployees) : 0;
                $counts[] = [
                    'present' => $present,
                    'late' => rand(0, 5)
                ];
            }
        }

        return [
            'dates' => $dates,
            'counts' => $counts,
            'total' => $totalEmployees
        ];
    }

    /**
     * Get today's attendance stats with more details
     */
    public function getTodayStats()
    {
        $stats = [
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'on_leave' => 0,
            'half_day' => 0,
            'rate' => 0,
            'early_departures' => 0
        ];

        try {
            $empManager = new EmployeeManager();
            $totalEmployees = $empManager->getEmployeeCounts()['total'];

            $tableCheck = $this->db->query("SHOW TABLES LIKE 'attendance'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $sql = "SELECT 
                            COUNT(DISTINCT employee_id) as present_count,
                            SUM(CASE WHEN TIME(am_time_in) > '08:00:00' OR TIME(pm_time_in) > '13:00:00' THEN 1 ELSE 0 END) as late_count,
                            SUM(CASE WHEN (am_time_out < '12:00:00' AND pm_time_in IS NULL) OR (am_time_out IS NULL AND pm_time_out < '17:00:00') THEN 1 ELSE 0 END) as half_day_count
                        FROM attendance 
                        WHERE date = CURDATE()";

                $result = $this->db->query($sql);
                if ($result && $row = $result->fetch_assoc()) {
                    $stats['present'] = (int)$row['present_count'];
                    $stats['late'] = (int)$row['late_count'];
                    $stats['half_day'] = (int)$row['half_day_count'];
                }
            }

            $stats['rate'] = $totalEmployees > 0 ? round(($stats['present'] / $totalEmployees) * 100) : 0;
            $stats['absent'] = $totalEmployees - $stats['present'];
        } catch (Exception $e) {
            error_log("Error getting today's attendance: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get monthly attendance summary
     */
    public function getMonthlySummary($year = null, $month = null)
    {
        $year = $year ?? date('Y');
        $month = $month ?? date('m');

        $summary = [
            'total_days' => 0,
            'avg_attendance' => 0,
            'avg_late' => 0,
            'best_day' => null,
            'worst_day' => null
        ];

        try {
            $sql = "SELECT 
                        COUNT(DISTINCT date) as days_count,
                        AVG(daily_present) as avg_present,
                        AVG(daily_late) as avg_late,
                        MAX(daily_present) as max_present,
                        MIN(daily_present) as min_present
                    FROM (
                        SELECT 
                            date,
                            COUNT(DISTINCT employee_id) as daily_present,
                            SUM(CASE WHEN TIME(am_time_in) > '08:00:00' OR TIME(pm_time_in) > '13:00:00' THEN 1 ELSE 0 END) as daily_late
                        FROM attendance 
                        WHERE YEAR(date) = ? AND MONTH(date) = ?
                        GROUP BY date
                    ) as daily_stats";

            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("ii", $year, $month);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $summary['total_days'] = $row['days_count'];
                $summary['avg_attendance'] = round($row['avg_present']);
                $summary['avg_late'] = round($row['avg_late']);
            }
        } catch (Exception $e) {
            error_log("Error getting monthly summary: " . $e->getMessage());
        }

        return $summary;
    }

    /**
     * Get employee attendance records
     */
    public function getEmployeeAttendance($employeeId, $limit = 10)
    {
        try {
            $sql = "SELECT * FROM attendance 
                    WHERE employee_id = ? 
                    ORDER BY date DESC 
                    LIMIT ?";

            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("si", $employeeId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting employee attendance: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Payroll Management Class - Enhanced
 */
class PayrollManager
{
    private $db;
    private $pdo;
    private $payrollTables = [
        'permanent' => 'payroll_history_permanent',
        'contractual' => 'payroll_history_contractual',
        'job_order' => 'payroll_history_joborder'
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->pdo = $this->db->getPDO();
    }

    /**
     * Get payroll summary for current month
     */
    public function getCurrentMonthSummary()
    {
        $summary = [
            'processed' => 0,
            'pending' => 0,
            'draft' => 0,
            'total' => 0,
            'total_amount' => 0,
            'by_type' => [
                'permanent' => ['count' => 0, 'amount' => 0],
                'contractual' => ['count' => 0, 'amount' => 0],
                'job_order' => ['count' => 0, 'amount' => 0]
            ]
        ];

        try {
            $currentMonth = date('Y-m');

            foreach ($this->payrollTables as $type => $table) {
                $tableCheck = $this->db->query("SHOW TABLES LIKE '$table'");
                if ($tableCheck && $tableCheck->num_rows > 0) {
                    // Processed payrolls (approved or paid)
                    $sql = "SELECT 
                                COUNT(*) as count, 
                                COALESCE(SUM(net_amount), 0) as total,
                                COALESCE(SUM(gross_amount), 0) as gross_total,
                                COALESCE(SUM(total_deductions), 0) as deductions_total
                            FROM $table 
                            WHERE payroll_period = ? 
                            AND status IN ('approved', 'paid')";

                    $stmt = $this->db->prepare($sql);
                    $stmt->bind_param("s", $currentMonth);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($row = $result->fetch_assoc()) {
                        $summary['processed'] += (int)$row['count'];
                        $summary['total_amount'] += (float)$row['total'];
                        $summary['by_type'][$type]['count'] = (int)$row['count'];
                        $summary['by_type'][$type]['amount'] = (float)$row['total'];
                        $summary['by_type'][$type]['gross'] = (float)$row['gross_total'];
                        $summary['by_type'][$type]['deductions'] = (float)$row['deductions_total'];
                    }

                    // Pending payrolls
                    $sql = "SELECT COUNT(*) as count 
                            FROM $table 
                            WHERE payroll_period = ? 
                            AND status = 'pending'";

                    $stmt = $this->db->prepare($sql);
                    $stmt->bind_param("s", $currentMonth);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($row = $result->fetch_assoc()) {
                        $summary['pending'] += (int)$row['count'];
                    }

                    // Draft payrolls
                    $sql = "SELECT COUNT(*) as count 
                            FROM $table 
                            WHERE payroll_period = ? 
                            AND status = 'draft'";

                    $stmt = $this->db->prepare($sql);
                    $stmt->bind_param("s", $currentMonth);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($row = $result->fetch_assoc()) {
                        $summary['draft'] += (int)$row['count'];
                    }
                }
            }

            $summary['total'] = $summary['processed'] + $summary['pending'] + $summary['draft'];
        } catch (Exception $e) {
            error_log("Error getting payroll summary: " . $e->getMessage());
        }

        return $summary;
    }

    /**
     * Get payroll history
     */
    public function getPayrollHistory($limit = 10)
    {
        $history = [];

        try {
            foreach ($this->payrollTables as $type => $table) {
                $tableCheck = $this->db->query("SHOW TABLES LIKE '$table'");
                if ($tableCheck && $tableCheck->num_rows > 0) {
                    $sql = "SELECT 
                                payroll_period,
                                payroll_cutoff,
                                COUNT(*) as employee_count,
                                SUM(gross_amount) as gross_total,
                                SUM(total_deductions) as deductions_total,
                                SUM(net_amount) as net_total,
                                status,
                                processed_date,
                                ? as employee_type
                            FROM $table 
                            GROUP BY payroll_period, payroll_cutoff, status, processed_date
                            ORDER BY processed_date DESC
                            LIMIT ?";

                    $stmt = $this->db->prepare($sql);
                    $stmt->bind_param("si", $type, $limit);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    while ($row = $result->fetch_assoc()) {
                        $history[] = $row;
                    }
                }
            }

            // Sort by date
            usort($history, function ($a, $b) {
                return strtotime($b['processed_date']) - strtotime($a['processed_date']);
            });

            // Limit results
            $history = array_slice($history, 0, $limit);
        } catch (Exception $e) {
            error_log("Error getting payroll history: " . $e->getMessage());
        }

        return $history;
    }

    /**
     * Get payroll by period
     */
    public function getPayrollByPeriod($period, $type = null)
    {
        $payrolls = [];

        try {
            $tables = $type ? [$type => $this->payrollTables[$type]] : $this->payrollTables;

            foreach ($tables as $empType => $table) {
                $tableCheck = $this->db->query("SHOW TABLES LIKE '$table'");
                if ($tableCheck && $tableCheck->num_rows > 0) {
                    $sql = "SELECT * FROM $table WHERE payroll_period = ?";

                    if ($type) {
                        $sql .= " AND employee_type = ?";
                        $stmt = $this->db->prepare($sql);
                        $stmt->bind_param("ss", $period, $empType);
                    } else {
                        $stmt = $this->db->prepare($sql);
                        $stmt->bind_param("s", $period);
                    }

                    $stmt->execute();
                    $result = $stmt->get_result();

                    while ($row = $result->fetch_assoc()) {
                        $row['employee_type'] = $empType;
                        $payrolls[] = $row;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error getting payroll by period: " . $e->getMessage());
        }

        return $payrolls;
    }

    /**
     * Get recent payroll activities
     */
    public function getRecentActivities($limit = 5)
    {
        $activities = [];

        try {
            foreach ($this->payrollTables as $type => $table) {
                $tableCheck = $this->db->query("SHOW TABLES LIKE '$table'");
                if ($tableCheck && $tableCheck->num_rows > 0) {
                    $typeLabel = ucfirst(str_replace('_', ' ', $type));

                    $sql = "SELECT 
                                'payroll' as activity_type,
                                ? as employee_type,
                                CONCAT('Payroll processed for ', COUNT(*), ' ', ?, ' employees') as description,
                                MAX(processed_date) as activity_date,
                                SUM(net_amount) as total_amount
                            FROM $table 
                            WHERE processed_date IS NOT NULL 
                            AND processed_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                            GROUP BY DATE(processed_date)
                            ORDER BY activity_date DESC
                            LIMIT 3";

                    $stmt = $this->db->prepare($sql);
                    $stmt->bind_param("ss", $typeLabel, $typeLabel);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    while ($row = $result->fetch_assoc()) {
                        if ($row['activity_date']) {
                            $activities[] = [
                                'type' => 'payroll',
                                'title' => 'Payroll Processed',
                                'description' => $row['description'],
                                'details' => 'Total amount: ' . Helpers::formatCurrency($row['total_amount']),
                                'time' => $row['activity_date'],
                                'icon' => 'money-bill-wave',
                                'icon_color' => 'warning'
                            ];
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error getting payroll activities: " . $e->getMessage());
        }

        return $activities;
    }

    /**
     * Get payroll statistics
     */
    public function getPayrollStatistics()
    {
        $stats = [
            'total_paid_this_year' => 0,
            'average_payroll' => 0,
            'highest_payroll_month' => null,
            'total_deductions' => 0
        ];

        try {
            $currentYear = date('Y');

            foreach ($this->payrollTables as $type => $table) {
                $tableCheck = $this->db->query("SHOW TABLES LIKE '$table'");
                if ($tableCheck && $tableCheck->num_rows > 0) {
                    $sql = "SELECT 
                                SUM(net_amount) as total,
                                AVG(net_amount) as average,
                                SUM(total_deductions) as deductions
                            FROM $table 
                            WHERE YEAR(processed_date) = ? 
                            AND status IN ('approved', 'paid')";

                    $stmt = $this->db->prepare($sql);
                    $stmt->bind_param("i", $currentYear);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($row = $result->fetch_assoc()) {
                        $stats['total_paid_this_year'] += (float)$row['total'];
                        $stats['total_deductions'] += (float)$row['deductions'];
                    }
                }
            }

            $stats['average_payroll'] = $stats['total_paid_this_year'] / 12;
        } catch (Exception $e) {
            error_log("Error getting payroll statistics: " . $e->getMessage());
        }

        return $stats;
    }
}

/**
 * Activity/Log Management Class - Enhanced
 */
class ActivityManager
{
    private $db;
    private $pdo;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->pdo = $this->db->getPDO();
    }

    /**
     * Get recent system activities
     */
    public function getRecentActivities($limit = 5)
    {
        $activities = [];

        try {
            // Get activities from audit_logs
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'audit_logs'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $sql = "SELECT 
                            al.*,
                            a.full_name as user_name,
                            a.profile_picture as user_avatar
                        FROM audit_logs al
                        LEFT JOIN admins a ON al.user_id = a.id
                        WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        ORDER BY al.created_at DESC
                        LIMIT ?";

                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("i", $limit);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $icon = 'info-circle';
                    $color = 'info';

                    if (strpos($row['action_type'], 'login') !== false) {
                        $icon = 'sign-in-alt';
                        $color = 'primary';
                    } elseif (strpos($row['action_type'], 'update') !== false) {
                        $icon = 'edit';
                        $color = 'warning';
                    } elseif (strpos($row['action_type'], 'create') !== false) {
                        $icon = 'plus-circle';
                        $color = 'success';
                    } elseif (strpos($row['action_type'], 'delete') !== false) {
                        $icon = 'trash';
                        $color = 'danger';
                    } elseif (strpos($row['action_type'], 'payroll') !== false) {
                        $icon = 'money-bill-wave';
                        $color = 'warning';
                    } elseif (strpos($row['action_type'], 'attendance') !== false) {
                        $icon = 'calendar-check';
                        $color = 'info';
                    }

                    $activities[] = [
                        'type' => 'system',
                        'title' => ucfirst(str_replace('_', ' ', $row['action_type'])),
                        'description' => $row['description'],
                        'user' => $row['user_name'] ?? 'System',
                        'user_avatar' => $row['user_avatar'] ?? null,
                        'ip_address' => $row['ip_address'],
                        'time' => $row['created_at'],
                        'icon' => $icon,
                        'icon_color' => $color
                    ];
                }
            }

            // Add employee activities
            $empManager = new EmployeeManager();
            $recentEmployees = $empManager->getRecentEmployees(3);

            foreach ($recentEmployees as $emp) {
                $activities[] = [
                    'type' => 'employee',
                    'title' => 'New Employee Added',
                    'description' => "{$emp['full_name']} joined as {$emp['type']}",
                    'details' => $emp['position'] ?? '',
                    'time' => $emp['created_at'],
                    'icon' => 'user-plus',
                    'icon_color' => 'success'
                ];
            }

            // Add payroll activities
            $payrollManager = new PayrollManager();
            $payrollActivities = $payrollManager->getRecentActivities(3);
            $activities = array_merge($activities, $payrollActivities);

            // Sort by date
            usort($activities, function ($a, $b) {
                return strtotime($b['time']) - strtotime($a['time']);
            });

            // Limit results
            $activities = array_slice($activities, 0, $limit);
        } catch (Exception $e) {
            error_log("Error getting activities: " . $e->getMessage());
        }

        return $activities;
    }

    /**
     * Log an activity
     */
    public function logActivity($userId, $actionType, $description, $ipAddress = null, $details = null)
    {
        try {
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'audit_logs'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $ipAddress = $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? null;
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

                $sql = "INSERT INTO audit_logs 
                        (user_id, action_type, description, ip_address, user_agent, details) 
                        VALUES (?, ?, ?, ?, ?, ?)";

                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("isssss", $userId, $actionType, $description, $ipAddress, $userAgent, $details);
                return $stmt->execute();
            }
        } catch (Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Get activity statistics
     */
    public function getActivityStats()
    {
        $stats = [
            'total_logins_today' => 0,
            'total_actions_today' => 0,
            'most_active_user' => null,
            'popular_actions' => []
        ];

        try {
            $sql = "SELECT 
                        COUNT(*) as total_actions,
                        SUM(CASE WHEN DATE(created_at) = CURDATE() AND action_type LIKE '%login%' THEN 1 ELSE 0 END) as logins_today,
                        user_id,
                        action_type,
                        COUNT(*) as action_count
                    FROM audit_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY user_id, action_type
                    ORDER BY action_count DESC
                    LIMIT 5";

            $result = $this->db->query($sql);

            while ($row = $result->fetch_assoc()) {
                $stats['popular_actions'][] = [
                    'action' => $row['action_type'],
                    'count' => $row['action_count']
                ];
                $stats['total_actions_today'] += $row['action_count'];
            }
        } catch (Exception $e) {
            error_log("Error getting activity stats: " . $e->getMessage());
        }

        return $stats;
    }
}

/**
 * File Upload Handler - Enhanced
 */
class FileUploader
{
    private $uploadDir;
    private $maxSize;
    private $allowedTypes;
    private $errors = [];

    public function __construct($uploadDir = './img/uploads/', $maxSize = 2097152)
    {
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->maxSize = $maxSize; // 2MB default
        $this->allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        // Create directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0755, true)) {
                $this->errors[] = "Failed to create upload directory";
            }
        }
    }

    /**
     * Upload profile picture
     */
    public function uploadProfilePicture($file, $userId)
    {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'No file uploaded or upload error'];
        }

        // Check file size
        if ($file['size'] > $this->maxSize) {
            return ['success' => false, 'error' => 'File size exceeds 2MB limit'];
        }

        // Check file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes)) {
            return ['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP'];
        }

        // Validate image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['success' => false, 'error' => 'File is not a valid image'];
        }

        // Generate secure filename
        $filename = 'admin_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $targetPath = $this->uploadDir . $filename;

        // Upload file
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Optimize image if it's too large
            $this->optimizeImage($targetPath, $extension);

            return [
                'success' => true,
                'path' => './img/uploads/' . $filename,
                'filename' => $filename,
                'size' => $file['size'],
                'type' => $extension
            ];
        }

        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }

    /**
     * Delete old profile picture
     */
    public function deleteFile($filePath)
    {
        if ($filePath && file_exists($filePath) && strpos($filePath, 'default') === false) {
            return unlink($filePath);
        }
        return false;
    }

    /**
     * Optimize image (resize if too large)
     */
    private function optimizeImage($path, $extension)
    {
        try {
            list($width, $height) = getimagesize($path);

            // Max dimensions
            $maxWidth = 800;
            $maxHeight = 800;

            if ($width <= $maxWidth && $height <= $maxHeight) {
                return;
            }

            // Calculate new dimensions
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = round($width * $ratio);
            $newHeight = round($height * $ratio);

            // Create image resource based on type
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $src = imagecreatefromjpeg($path);
                    break;
                case 'png':
                    $src = imagecreatefrompng($path);
                    break;
                case 'gif':
                    $src = imagecreatefromgif($path);
                    break;
                default:
                    return;
            }

            // Create new image
            $dst = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG
            if ($extension === 'png') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $transparent);
            }

            // Resize
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            // Save
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($dst, $path, 85);
                    break;
                case 'png':
                    imagepng($dst, $path, 6);
                    break;
                case 'gif':
                    imagegif($dst, $path);
                    break;
            }

            // Clean up
            imagedestroy($src);
            imagedestroy($dst);
        } catch (Exception $e) {
            error_log("Image optimization error: " . $e->getMessage());
        }
    }

    /**
     * Get errors
     */
    public function getErrors()
    {
        return $this->errors;
    }
}

/**
 * Notification Manager - New
 */
class NotificationManager
{
    private $db;
    private $pdo;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->pdo = $this->db->getPDO();
    }

    /**
     * Get notifications for current user
     */
    public function getUserNotifications($userId, $limit = 5)
    {
        $notifications = [];

        try {
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'notifications'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $sql = "SELECT * FROM notifications 
                        WHERE user_id = ? OR user_id IS NULL
                        ORDER BY created_at DESC 
                        LIMIT ?";

                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("ii", $userId, $limit);
                $stmt->execute();
                $result = $stmt->get_result();

                $notifications = $result->fetch_all(MYSQLI_ASSOC);
            }

            // If no notifications table, generate system notifications
            if (empty($notifications)) {
                $notifications = $this->generateSystemNotifications();
            }
        } catch (Exception $e) {
            error_log("Error getting notifications: " . $e->getMessage());
        }

        return $notifications;
    }

    /**
     * Generate system notifications
     */
    private function generateSystemNotifications()
    {
        $notifications = [];

        try {
            // Check expiring contracts
            $empManager = new EmployeeManager();
            $expiring = $empManager->getExpiringContracts(30);

            if (!empty($expiring)) {
                $notifications[] = [
                    'title' => 'Expiring Contracts',
                    'message' => count($expiring) . ' contracts expiring within 30 days',
                    'type' => 'warning',
                    'icon' => 'file-contract',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }

            // Check pending payroll
            $payrollManager = new PayrollManager();
            $summary = $payrollManager->getCurrentMonthSummary();

            if ($summary['pending'] > 0) {
                $notifications[] = [
                    'title' => 'Pending Payroll',
                    'message' => $summary['pending'] . ' payrolls pending approval',
                    'type' => 'info',
                    'icon' => 'money-bill-wave',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }

            // Check attendance rate
            $attendanceManager = new AttendanceManager();
            $todayStats = $attendanceManager->getTodayStats();

            if ($todayStats['rate'] < 70) {
                $notifications[] = [
                    'title' => 'Low Attendance',
                    'message' => 'Today\'s attendance rate is ' . $todayStats['rate'] . '%',
                    'type' => 'danger',
                    'icon' => 'calendar-times',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        } catch (Exception $e) {
            error_log("Error generating notifications: " . $e->getMessage());
        }

        return $notifications;
    }

    /**
     * Create notification
     */
    public function createNotification($userId, $title, $message, $type = 'info', $link = null)
    {
        try {
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'notifications'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $sql = "INSERT INTO notifications (user_id, title, message, type, link) 
                        VALUES (?, ?, ?, ?, ?)";

                $stmt = $this->db->prepare($sql);
                $stmt->bind_param("issss", $userId, $title, $message, $type, $link);
                return $stmt->execute();
            }
        } catch (Exception $e) {
            error_log("Error creating notification: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId)
    {
        try {
            $sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $notificationId);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
        }

        return false;
    }
}

/**
 * Helper Functions - Enhanced
 */
class Helpers
{
    /**
     * Format time elapsed
     */
    public static function timeElapsedString($datetime, $full = false)
    {
        try {
            $now = new DateTime();
            $ago = new DateTime($datetime);
            $diff = $now->diff($ago);

            // Get the difference in a safe way
            $years = $diff->y;
            $months = $diff->m;
            $days = $diff->d;
            $hours = $diff->h;
            $minutes = $diff->i;
            $seconds = $diff->s;

            // Calculate weeks from days
            $weeks = floor($days / 7);
            $remaining_days = $days % 7;

            $string = [];

            if ($years > 0) {
                $string[] = $years . ' year' . ($years > 1 ? 's' : '');
            }

            if ($months > 0) {
                $string[] = $months . ' month' . ($months > 1 ? 's' : '');
            }

            if ($weeks > 0) {
                $string[] = $weeks . ' week' . ($weeks > 1 ? 's' : '');
            }

            if ($remaining_days > 0) {
                $string[] = $remaining_days . ' day' . ($remaining_days > 1 ? 's' : '');
            }

            if ($hours > 0) {
                $string[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
            }

            if ($minutes > 0) {
                $string[] = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
            }

            if ($seconds > 0 && empty($string)) {
                $string[] = $seconds . ' second' . ($seconds > 1 ? 's' : '');
            }

            if (!$full) {
                $string = array_slice($string, 0, 1);
            }

            return !empty($string) ? implode(', ', $string) . ' ago' : 'just now';
        } catch (Exception $e) {
            error_log("Time elapsed error: " . $e->getMessage());
            return 'recently';
        }
    }

    /**
     * Format date
     */
    public static function formatDate($date, $format = 'F j, Y')
    {
        if (empty($date)) return 'N/A';

        try {
            return date($format, strtotime($date));
        } catch (Exception $e) {
            return $date;
        }
    }

    /**
     * Format datetime
     */
    public static function formatDateTime($datetime, $format = 'F j, Y g:i A')
    {
        if (empty($datetime)) return 'N/A';

        try {
            return date($format, strtotime($datetime));
        } catch (Exception $e) {
            return $datetime;
        }
    }

    /**
     * Format time
     */
    public static function formatTime($time, $format = 'g:i A')
    {
        if (empty($time)) return 'N/A';

        try {
            return date($format, strtotime($time));
        } catch (Exception $e) {
            return $time;
        }
    }

    /**
     * Sanitize input
     */
    public static function sanitize($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format number
     */
    public static function formatNumber($number, $decimals = 0)
    {
        if (!is_numeric($number)) return '0';
        return number_format($number, $decimals);
    }

    /**
     * Format currency
     */
    public static function formatCurrency($amount)
    {
        if (!is_numeric($amount)) return '₱0.00';
        return '₱' . number_format($amount, 2);
    }

    /**
     * Get percentage
     */
    public static function getPercentage($value, $total)
    {
        if ($total <= 0 || !is_numeric($value) || !is_numeric($total)) return 0;
        return round(($value / $total) * 100, 1);
    }

    /**
     * Truncate text
     */
    public static function truncate($text, $length = 50, $suffix = '...')
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length) . $suffix;
    }

    /**
     * Get status badge class
     */
    public static function getStatusBadgeClass($status)
    {
        $classes = [
            'active' => 'bg-green-100 text-green-800 border-green-200',
            'inactive' => 'bg-red-100 text-red-800 border-red-200',
            'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
            'approved' => 'bg-blue-100 text-blue-800 border-blue-200',
            'paid' => 'bg-purple-100 text-purple-800 border-purple-200',
            'draft' => 'bg-gray-100 text-gray-800 border-gray-200',
            'cancelled' => 'bg-red-100 text-red-800 border-red-200',
            'completed' => 'bg-green-100 text-green-800 border-green-200',
            'processing' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
            'on_leave' => 'bg-orange-100 text-orange-800 border-orange-200',
            'late' => 'bg-red-100 text-red-800 border-red-200',
            'present' => 'bg-green-100 text-green-800 border-green-200',
            'absent' => 'bg-red-100 text-red-800 border-red-200'
        ];

        $status = strtolower($status);
        return $classes[$status] ?? 'bg-gray-100 text-gray-800 border-gray-200';
    }

    /**
     * Get status label
     */
    public static function getStatusLabel($status)
    {
        return ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Get icon for employee type
     */
    public static function getEmployeeTypeIcon($type)
    {
        $icons = [
            'permanent' => 'user-tie',
            'contractual' => 'file-contract',
            'job_order' => 'tasks',
            'joborder' => 'tasks',
            'contract_of_service' => 'file-signature'
        ];

        return $icons[strtolower($type)] ?? 'user';
    }

    /**
     * Get color for employee type
     */
    public static function getEmployeeTypeColor($type)
    {
        $colors = [
            'permanent' => '#3b82f6',
            'contractual' => '#f59e0b',
            'job_order' => '#10b981',
            'joborder' => '#10b981',
            'contract_of_service' => '#8b5cf6'
        ];

        return $colors[strtolower($type)] ?? '#6b7280';
    }

    /**
     * Generate random color
     */
    public static function randomColor($index = null)
    {
        $colors = [
            '#1e40af',
            '#3b82f6',
            '#60a5fa',
            '#93c5fd',
            '#6366f1',
            '#8b5cf6',
            '#a78bfa',
            '#c4b5fd',
            '#0ea5e9',
            '#06b6d4',
            '#22d3ee',
            '#67e8f9',
            '#10b981',
            '#34d399',
            '#6ee7b7',
            '#a7f3d0',
            '#f59e0b',
            '#fbbf24',
            '#fcd34d',
            '#fde68a',
            '#ef4444',
            '#f87171',
            '#fca5a5',
            '#fecaca'
        ];

        if ($index !== null) {
            return $colors[$index % count($colors)];
        }

        return $colors[array_rand($colors)];
    }

    /**
     * Get greeting based on time
     */
    public static function getGreeting()
    {
        $hour = date('H');

        if ($hour < 12) {
            return 'Good Morning';
        } elseif ($hour < 17) {
            return 'Good Afternoon';
        } elseif ($hour < 22) {
            return 'Good Evening';
        } else {
            return 'Good Night';
        }
    }

    /**
     * Format file size
     */
    public static function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Generate initials from name
     */
    public static function getInitials($name)
    {
        $words = explode(' ', $name);
        $initials = '';

        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper($word[0]);
            }
        }

        return substr($initials, 0, 2);
    }

    /**
     * Get avatar URL with fallback
     */
    public static function getAvatarUrl($path, $name = null)
    {
        if (!empty($path) && file_exists($path)) {
            return $path;
        }

        if ($name) {
            return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=1e40af&color=fff&size=128';
        }

        return './img/default-avatar.png';
    }

    /**
     * Check if request is AJAX
     */
    public static function isAjax()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Get client IP address
     */
    public static function getClientIP()
    {
        $ipaddress = '';

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        }

        return $ipaddress;
    }

    /**
     * Get browser info
     */
    public static function getBrowserInfo()
    {
        $u_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $bname = 'Unknown';
        $platform = 'Unknown';
        $version = '';

        // Get platform
        if (preg_match('/linux/i', $u_agent)) {
            $platform = 'Linux';
        } elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
            $platform = 'Mac';
        } elseif (preg_match('/windows|win32/i', $u_agent)) {
            $platform = 'Windows';
        }

        // Get browser name
        if (preg_match('/MSIE/i', $u_agent) && !preg_match('/Opera/i', $u_agent)) {
            $bname = 'Internet Explorer';
            $ub = "MSIE";
        } elseif (preg_match('/Firefox/i', $u_agent)) {
            $bname = 'Mozilla Firefox';
            $ub = "Firefox";
        } elseif (preg_match('/Chrome/i', $u_agent)) {
            $bname = 'Google Chrome';
            $ub = "Chrome";
        } elseif (preg_match('/Safari/i', $u_agent)) {
            $bname = 'Apple Safari';
            $ub = "Safari";
        } elseif (preg_match('/Opera/i', $u_agent)) {
            $bname = 'Opera';
            $ub = "Opera";
        } elseif (preg_match('/Netscape/i', $u_agent)) {
            $bname = 'Netscape';
            $ub = "Netscape";
        }

        // Get version
        $known = array('Version', $ub, 'other');
        $pattern = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
        if (preg_match_all($pattern, $u_agent, $matches)) {
            $i = count($matches['browser']);
            if ($i != 1) {
                if (strripos($u_agent, "Version") < strripos($u_agent, $ub)) {
                    $version = $matches['version'][0];
                } else {
                    $version = $matches['version'][1];
                }
            } else {
                $version = $matches['version'][0];
            }
        }

        return [
            'name' => $bname,
            'version' => $version,
            'platform' => $platform,
            'user_agent' => $u_agent
        ];
    }
}

// ===============================================
// INITIALIZATION & DATA FETCHING
// ===============================================

try {
    // Initialize database connection
    $db = Database::getInstance();

    // Initialize managers
    $adminManager = new AdminManager();
    $empManager = new EmployeeManager();
    $attendanceManager = new AttendanceManager();
    $payrollManager = new PayrollManager();
    $activityManager = new ActivityManager();
    $notificationManager = new NotificationManager();
    $fileUploader = new FileUploader();

    // Get current admin data
    $adminId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $adminData = $adminManager->getCurrentAdmin($adminId);

    if (!$adminData) {
        // Fallback to session data
        $adminData = [
            'id' => $adminId,
            'full_name' => $_SESSION['user_name'] ?? 'Administrator',
            'email' => $_SESSION['user_email'] ?? 'admin@paluan.gov.ph',
            'first_name' => $_SESSION['user_first_name'] ?? '',
            'last_name' => $_SESSION['user_last_name'] ?? '',
            'position' => $_SESSION['user_role'] ?? 'Administrator',
            'department' => 'Administration',
            'profile_picture' => $_SESSION['user_avatar'] ?? './img/admin1.png',
            'last_login' => $_SESSION['login_time'] ?? date('Y-m-d H:i:s'),
            'is_super_admin' => 0,
            'is_active' => 1
        ];
    } else {
        // Update session with fresh data
        $_SESSION['user_name'] = $adminData['full_name'];
        $_SESSION['user_email'] = $adminData['email'];
        $_SESSION['user_first_name'] = $adminData['first_name'];
        $_SESSION['user_last_name'] = $adminData['last_name'];
        $_SESSION['user_role'] = $adminData['position'] ?? 'Administrator';
        $_SESSION['user_avatar'] = $adminData['profile_picture'] ?? './img/admin1.png';
        $_SESSION['login_time'] = strtotime($adminData['last_login'] ?? date('Y-m-d H:i:s'));
    }

    // Generate CSRF token
    $csrf_token = Helpers::generateCSRFToken();

    // ===============================================
    // HANDLE PROFILE UPDATE
    // ===============================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !Helpers::verifyCSRFToken($_POST['csrf_token'])) {
            $_SESSION['profile_update_error'] = 'Invalid security token. Please try again.';
        } else {
            $updateData = [
                'full_name' => $_POST['full_name'] ?? $adminData['full_name'],
                'email' => $_POST['email'] ?? $adminData['email'],
                'first_name' => $_POST['first_name'] ?? '',
                'last_name' => $_POST['last_name'] ?? ''
            ];

            // Validate email
            if (!filter_var($updateData['email'], FILTER_VALIDATE_EMAIL)) {
                $_SESSION['profile_update_error'] = 'Invalid email format.';
            } else {
                $profilePicture = null;

                // Handle file upload
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = $fileUploader->uploadProfilePicture($_FILES['profile_picture'], $adminId);

                    if ($uploadResult['success']) {
                        $profilePicture = $uploadResult['path'];

                        // Delete old picture if not default
                        if (
                            !empty($adminData['profile_picture']) &&
                            $adminData['profile_picture'] !== './img/admin1.png' &&
                            strpos($adminData['profile_picture'], 'default') === false &&
                            file_exists($adminData['profile_picture'])
                        ) {
                            $fileUploader->deleteFile($adminData['profile_picture']);
                        }
                    } else {
                        $_SESSION['profile_update_error'] = $uploadResult['error'];
                    }
                }

                // Update profile
                if (!isset($_SESSION['profile_update_error'])) {
                    if ($adminManager->updateProfile($adminId, $updateData, $profilePicture)) {
                        $_SESSION['profile_update_success'] = 'Profile updated successfully!';

                        // Update session
                        $_SESSION['user_name'] = $updateData['full_name'];
                        $_SESSION['user_email'] = $updateData['email'];
                        $_SESSION['user_first_name'] = $updateData['first_name'];
                        $_SESSION['user_last_name'] = $updateData['last_name'];
                        if ($profilePicture) {
                            $_SESSION['user_avatar'] = $profilePicture;
                        }

                        // Log activity
                        $activityManager->logActivity(
                            $adminId,
                            'profile_update',
                            'Updated profile information',
                            Helpers::getClientIP(),
                            json_encode(['fields' => array_keys($updateData)])
                        );
                    } else {
                        $_SESSION['profile_update_error'] = 'Failed to update profile. Please try again.';
                    }
                }
            }
        }

        // Redirect to refresh page
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    // ===============================================
    // HANDLE LOGOUT
    // ===============================================
    if (isset($_GET['logout'])) {
        // Update last login before logout
        $adminManager->updateLastLogin($adminId);

        // Log activity
        $activityManager->logActivity(
            $adminId,
            'logout',
            'Logged out',
            Helpers::getClientIP()
        );

        // Clear session
        $_SESSION = [];

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
        }

        session_destroy();

        // Clear remember me cookie
        if (isset($_COOKIE['remember_user'])) {
            setcookie('remember_user', '', time() - 3600, "/", "", true, true);
        }

        header('Location: login.php');
        exit();
    }

    // ===============================================
    // HANDLE AJAX REQUESTS
    // ===============================================
    if (Helpers::isAjax()) {
        header('Content-Type: application/json');

        $action = $_GET['action'] ?? $_POST['action'] ?? '';

        switch ($action) {
            case 'get_attendance_data':
                $attendanceData = $attendanceManager->getWeeklyTrend();
                echo json_encode(['success' => true, 'data' => $attendanceData]);
                exit;

            case 'get_payroll_summary':
                $summary = $payrollManager->getCurrentMonthSummary();
                echo json_encode(['success' => true, 'data' => $summary]);
                exit;

            case 'get_notifications':
                $notifications = $notificationManager->getUserNotifications($adminId, 5);
                echo json_encode(['success' => true, 'data' => $notifications]);
                exit;

            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
                exit;
        }
    }

    // ===============================================
    // FETCH DASHBOARD DATA
    // ===============================================

    // Employee counts
    $employeeCounts = $empManager->getEmployeeCounts();

    // Department distribution
    $departmentData = $empManager->getDepartmentDistribution();
    $departments = $departmentData['data'];
    $departmentColors = $departmentData['colors'];
    $departmentBreakdown = $departmentData['breakdown'];

    // Attendance data
    $attendanceData = $attendanceManager->getWeeklyTrend();
    $attendanceDates = array_map(function ($date) {
        return $date;
    }, $attendanceData['dates']);
    $attendancePresent = array_column($attendanceData['counts'], 'present');
    $attendanceLate = array_column($attendanceData['counts'], 'late');

    // Today's attendance stats
    $todayAttendance = $attendanceManager->getTodayStats();

    // Payroll summary
    $payrollSummary = $payrollManager->getCurrentMonthSummary();

    // Recent activities
    $recentActivities = $activityManager->getRecentActivities(8);

    // Notifications
    $notifications = $notificationManager->getUserNotifications($adminId, 5);

    // Prepare chart data - FIXED: Better handling for department chart
    $deptNames = array_keys($departments);
    $deptCounts = array_values($departments);
    $deptColors = array_values($departmentColors);

    // Limit to top 7 departments + Others (more accurate representation)
    if (count($deptNames) > 7) {
        // Sort departments by count (already sorted from query)
        $topDepartments = array_slice($departments, 0, 7, true);
        $otherCount = array_sum(array_slice($departments, 7));

        $deptNames = array_keys($topDepartments);
        $deptNames[] = 'Others';
        $deptCounts = array_values($topDepartments);
        $deptCounts[] = $otherCount;
        $deptColors = array_slice($deptColors, 0, 7);
        $deptColors[] = '#94a3b8'; // Gray color for Others
    }

    // Prepare JSON for charts
    $deptSeriesJson = json_encode($deptCounts, JSON_NUMERIC_CHECK);
    $deptLabelsJson = json_encode($deptNames, JSON_UNESCAPED_UNICODE);
    $deptColorsJson = json_encode($deptColors, JSON_UNESCAPED_SLASHES);
    $attendancePresentJson = json_encode($attendancePresent, JSON_NUMERIC_CHECK);
    $attendanceLateJson = json_encode($attendanceLate, JSON_NUMERIC_CHECK);
    $attendanceLabelsJson = json_encode($attendanceDates, JSON_UNESCAPED_UNICODE);

    // Calculate percentages
    $attendanceRate = $todayAttendance['rate'];
    $payrollProcessedPercent = $payrollSummary['total'] > 0 ?
        round(($payrollSummary['processed'] / $payrollSummary['total']) * 100) : 0;
    $payrollPendingPercent = $payrollSummary['total'] > 0 ?
        round(($payrollSummary['pending'] / $payrollSummary['total']) * 100) : 0;

    // Employee types for display
    $employeeTypes = [
        'Permanent' => $employeeCounts['permanent'],
        'Contractual' => $employeeCounts['contractual'],
        'Job Order' => $employeeCounts['job_order']
    ];

    // Gender distribution
    $genderDistribution = [
        'Male' => $employeeCounts['by_gender']['male'],
        'Female' => $employeeCounts['by_gender']['female'],
        'Other' => $employeeCounts['by_gender']['other']
    ];
} catch (Exception $e) {
    error_log("Dashboard initialization error: " . $e->getMessage());

    // Fallback data
    $adminData = [
        'id' => $_SESSION['user_id'] ?? 1,
        'full_name' => $_SESSION['user_name'] ?? 'Administrator',
        'email' => $_SESSION['user_email'] ?? 'admin@paluan.gov.ph',
        'first_name' => $_SESSION['user_first_name'] ?? '',
        'last_name' => $_SESSION['user_last_name'] ?? '',
        'position' => $_SESSION['user_role'] ?? 'Administrator',
        'department' => 'Administration',
        'profile_picture' => $_SESSION['user_avatar'] ?? './img/admin1.png',
        'last_login' => date('Y-m-d H:i:s', $_SESSION['login_time'] ?? time()),
        'is_super_admin' => 0,
        'is_active' => 1
    ];

    $employeeCounts = [
        'permanent' => 45,
        'contractual' => 32,
        'job_order' => 23,
        'total' => 100,
        'by_gender' => ['male' => 60, 'female' => 38, 'other' => 2],
        'by_status' => ['active' => 100, 'inactive' => 0]
    ];

    $departments = ['HR' => 25, 'Finance' => 18, 'IT' => 15, 'Admin' => 12, 'Operations' => 10, 'Engineering' => 8, 'Sales' => 7, 'Others' => 5];
    $departmentColors = [
        'HR' => '#1e40af',
        'Finance' => '#3b82f6',
        'IT' => '#60a5fa',
        'Admin' => '#6366f1',
        'Operations' => '#8b5cf6',
        'Engineering' => '#a78bfa',
        'Sales' => '#c4b5fd',
        'Others' => '#94a3b8'
    ];

    $attendanceDates = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $attendancePresent = [85, 88, 92, 87, 90, 75, 70];
    $attendanceLate = [5, 3, 4, 6, 2, 1, 0];

    $todayAttendance = ['present' => 85, 'late' => 5, 'absent' => 10, 'on_leave' => 0, 'half_day' => 3, 'rate' => 85];
    $payrollSummary = [
        'processed' => 75,
        'pending' => 15,
        'draft' => 10,
        'total' => 100,
        'total_amount' => 2500000,
        'by_type' => [
            'permanent' => ['count' => 40, 'amount' => 1500000],
            'contractual' => ['count' => 25, 'amount' => 700000],
            'job_order' => ['count' => 10, 'amount' => 300000]
        ]
    ];

    $recentActivities = [];
    $notifications = [];

    $deptNames = array_keys($departments);
    $deptCounts = array_values($departments);
    $deptColors = array_values($departmentColors);

    $deptSeriesJson = json_encode($deptCounts);
    $deptLabelsJson = json_encode($deptNames);
    $deptColorsJson = json_encode($deptColors);
    $attendancePresentJson = json_encode($attendancePresent);
    $attendanceLateJson = json_encode($attendanceLate);
    $attendanceLabelsJson = json_encode($attendanceDates);

    $attendanceRate = 85;
    $payrollProcessedPercent = 75;
    $payrollPendingPercent = 15;

    $employeeTypes = [
        'Permanent' => 45,
        'Contractual' => 32,
        'Job Order' => 23
    ];

    $genderDistribution = [
        'Male' => 60,
        'Female' => 38,
        'Other' => 2
    ];
}

// Set content type header
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="HRMS Dashboard - Municipality of Paluan">
    <meta name="author" content="Paluan LGU">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>HRMS Dashboard - Municipality of Paluan</title>

    <!-- CSS Libraries -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.1.1/flowbite.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        /* ===== CSS VARIABLES ===== */
        :root {
            --primary: #1e40af;
            --primary-light: #3b82f6;
            --primary-dark: #1e3a8a;
            --secondary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --dark: #1f2937;
            --light: #f8fafc;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;

            --gradient-primary: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            --gradient-secondary: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            --gradient-success: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            --gradient-danger: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
            --gradient-info: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);

            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --shadow-card: 0 8px 30px rgba(0, 0, 0, 0.08);

            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-2xl: 24px;

            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f8fafc 100%);
            min-height: 100vh;
            color: var(--dark);
        }

        /* ===== UTILITY CLASSES ===== */
        .text-gradient-primary {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .bg-gradient-primary {
            background: var(--gradient-primary);
        }

        .bg-gradient-success {
            background: var(--gradient-success);
        }

        .bg-gradient-warning {
            background: var(--gradient-warning);
        }

        .bg-gradient-danger {
            background: var(--gradient-danger);
        }

        .bg-gradient-secondary {
            background: var(--gradient-secondary);
        }

        .bg-gradient-info {
            background: var(--gradient-info);
        }

        /* ===== NAVBAR - UPDATED to match other pages ===== */
        .navbar {
            background: var(--gradient-primary);
            box-shadow: var(--shadow-md);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 70px;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            padding: 0 1.5rem;
            max-width: 100%;
        }

        .navbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .mobile-toggle {
            display: none;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            color: white;
        }

        .mobile-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }

        .mobile-toggle i {
            font-size: 1.25rem;
            color: white;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
        }

        .navbar-brand:hover {
            transform: translateY(-2px);
        }

        .brand-logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        .brand-text {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: white;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }

        .brand-subtitle {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        .datetime-container {
            display: flex;
            gap: 1rem;
        }

        .datetime-box {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-md);
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }

        .datetime-box:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .datetime-icon {
            color: white;
            font-size: 1rem;
        }

        .datetime-text {
            display: flex;
            flex-direction: column;
        }

        .datetime-label {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        .datetime-value {
            font-size: 0.85rem;
            font-weight: 700;
            color: white;
        }

        /* Notification Bell */
        .notification-container {
            position: relative;
        }

        .notification-button {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .notification-button:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--primary);
        }

        .notification-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            min-width: 350px;
            max-width: 400px;
            display: none;
            z-index: 1001;
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .notification-dropdown.active {
            display: block;
            animation: slideDown 0.3s ease;
        }

        .notification-header {
            padding: 1.25rem;
            background: var(--gradient-primary);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            font-size: 1rem;
            font-weight: 600;
        }

        .notification-header a {
            color: white;
            font-size: 0.85rem;
            opacity: 0.9;
            text-decoration: none;
        }

        .notification-header a:hover {
            opacity: 1;
            text-decoration: underline;
        }

        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--gray-100);
            transition: var(--transition);
            cursor: pointer;
        }

        .notification-item:hover {
            background: var(--gray-50);
            transform: translateX(5px);
        }

        .notification-item.unread {
            background: rgba(59, 130, 246, 0.05);
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .notification-icon.success {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .notification-icon.warning {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
        }

        .notification-icon.primary {
            background: rgba(30, 64, 175, 0.15);
            color: var(--primary);
        }

        .notification-icon.danger {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }

        .notification-icon.info {
            background: rgba(59, 130, 246, 0.15);
            color: var(--info);
        }

        .notification-content {
            flex: 1;
        }

        .notification-content h4 {
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .notification-content p {
            color: var(--gray-600);
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
        }

        .notification-time {
            color: var(--gray-500);
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .notification-footer {
            padding: 0.75rem 1.25rem;
            text-align: center;
            border-top: 1px solid var(--gray-200);
        }

        .notification-footer a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .notification-footer a:hover {
            text-decoration: underline;
        }

        /* User Menu */
        .user-menu {
            position: relative;
        }

        .user-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            padding: 0.3rem 0.6rem 0.3rem 0.3rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .user-button:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-name {
            font-size: 0.85rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }

        .user-role {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            min-width: 220px;
            display: none;
            z-index: 1001;
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .user-dropdown.active {
            display: block;
            animation: slideDown 0.3s ease;
        }

        .dropdown-header {
            padding: 1.25rem;
            background: var(--gradient-primary);
            color: white;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1.25rem;
            color: var(--gray-700);
            text-decoration: none;
            transition: var(--transition);
            border-bottom: 1px solid var(--gray-100);
            cursor: pointer;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item:hover {
            background: var(--gray-50);
            color: var(--primary);
            padding-left: 1.5rem;
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            color: var(--primary-light);
        }

        .dropdown-item.logout:hover {
            background: #fef2f2;
            color: var(--danger);
        }

        .dropdown-item.logout:hover i {
            color: var(--danger);
        }

        /* ===== SIDEBAR - UPDATED to match other pages ===== */
        .sidebar-container {
            position: fixed;
            left: 0;
            top: 70px;
            width: 260px;
            height: calc(100vh - 70px);
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 100%);
            z-index: 999;
            transition: transform 0.3s ease;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }

        .sidebar {
            height: 100%;
            padding: 1.5rem 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar-content {
            flex: 1;
            padding: 0 1rem;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.9rem 1.25rem;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: var(--transition);
            border-radius: var(--radius-md);
            margin-bottom: 0.25rem;
            position: relative;
            overflow: hidden;
        }

        .sidebar-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: white;
            transform: scaleY(0);
            transition: transform 0.3s ease;
            border-radius: 0 4px 4px 0;
        }

        .sidebar-item:hover {
            background: rgba(255, 255, 255, 0.12);
            color: white;
            transform: translateX(4px);
        }

        .sidebar-item:hover::before {
            transform: scaleY(1);
        }

        .sidebar-item.active {
            background: rgba(255, 255, 255, 0.18);
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .sidebar-item.active::before {
            transform: scaleY(1);
        }

        .sidebar-item i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .sidebar-item span {
            font-size: 0.95rem;
            font-weight: 600;
            flex: 1;
        }

        .sidebar-dropdown-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            margin-left: 2.5rem;
        }

        .sidebar-dropdown-menu.open {
            max-height: 500px;
        }

        .sidebar-dropdown-item {
            display: flex;
            align-items: center;
            padding: 0.7rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 0.25rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .sidebar-dropdown-item:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-dropdown-item i {
            font-size: 0.7rem;
            margin-right: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
            transition: color 0.3s ease;
        }

        .sidebar-dropdown-item:hover i {
            color: white;
        }

        .chevron {
            transition: transform 0.3s ease;
        }

        .chevron.rotated {
            transform: rotate(180deg);
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: white;
        }

        .sidebar-footer p {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 2rem;
            min-height: calc(100vh - 70px);
            transition: margin-left 0.3s ease;
            background: linear-gradient(135deg, #f8fafc 0%, #f0f9ff 50%, #e0f2fe 100%);
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
        }

        /* ===== OVERLAY FOR MOBILE ===== */
        .overlay {
            position: fixed;
            top: 70px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 998;
            display: none;
        }

        .overlay.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* ===== DASHBOARD HEADER ===== */
        .dashboard-header {
            margin-bottom: 2rem;
            animation: slideUp 0.6s ease-out;
        }

        .header-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            box-shadow: var(--shadow-card);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .header-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--success), var(--warning));
        }

        .dashboard-title {
            font-size: clamp(1.5rem, 3vw, 2.5rem);
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .dashboard-title span {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .dashboard-subtitle {
            color: var(--gray-600);
            font-size: 1rem;
            line-height: 1.5;
        }

        .welcome-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 50px;
            padding: 0.5rem 1rem;
            margin-top: 1rem;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .welcome-badge i {
            color: var(--primary);
        }

        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.25rem;
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-card);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 280px;
            width: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        .stat-card:hover::after {
            opacity: 1;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
            flex-shrink: 0;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }

        .stat-icon.primary {
            background: rgba(30, 64, 175, 0.1);
            color: var(--primary);
            border: 2px solid rgba(30, 64, 175, 0.2);
        }

        .stat-icon.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 2px solid rgba(16, 185, 129, 0.2);
        }

        .stat-icon.warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border: 2px solid rgba(245, 158, 11, 0.2);
        }

        .stat-icon.info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
            border: 2px solid rgba(59, 130, 246, 0.2);
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.8);
            flex-shrink: 0;
        }

        .trend-up {
            color: var(--success);
        }

        .trend-down {
            color: var(--danger);
        }

        .stat-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .stat-content h3 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0.5rem 0 0.25rem;
            line-height: 1;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
        }

        .stat-detail {
            color: var(--gray-500);
            font-size: 0.8rem;
            line-height: 1.3;
            margin-bottom: 0.75rem;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .stat-progress {
            height: 5px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 3px;
            overflow: hidden;
            margin-top: auto;
            flex-shrink: 0;
        }

        .stat-progress-bar {
            height: 100%;
            border-radius: 3px;
            transition: width 1s ease;
        }

        .stat-card.primary .stat-progress-bar {
            background: var(--gradient-primary);
        }

        .stat-card.success .stat-progress-bar {
            background: var(--gradient-success);
        }

        .stat-card.warning .stat-progress-bar {
            background: var(--gradient-warning);
        }

        .stat-card.info .stat-progress-bar {
            background: var(--gradient-info);
        }

        /* ===== CHARTS GRID ===== */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        .chart-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-card);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 500px;
        }

        .chart-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-shrink: 0;
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .chart-title i {
            width: 40px;
            height: 40px;
            background: var(--gradient-primary);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }

        .chart-subtitle {
            color: var(--gray-500);
            font-size: 0.85rem;
            margin-left: 3.5rem;
            margin-top: -0.25rem;
            margin-bottom: 0;
        }

        .chart-actions {
            display: flex;
            gap: 0.5rem;
            background: var(--gray-100);
            padding: 0.25rem;
            border-radius: var(--radius-lg);
            flex-shrink: 0;
        }

        .chart-action-btn {
            background: transparent;
            border: none;
            border-radius: var(--radius-md);
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            color: var(--gray-600);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            border: 1px solid transparent;
        }

        .chart-action-btn:hover {
            background: var(--gray-200);
            color: var(--primary);
        }

        .chart-action-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: var(--shadow-sm);
            border-color: var(--gray-200);
        }

        /* Chart Container - FIXED: No extra spacing */
        .chart-container {
            position: relative;
            width: 100%;
            flex: 1;
            min-height: 350px;
            margin: 0;
            padding: 0;
            overflow: visible;
        }

        /* Make ApexCharts fill the container with no gaps */
        #department-chart,
        #attendance-chart {
            width: 100%;
            height: 100%;
            min-height: 350px;
            margin: 0;
            padding: 0;
        }

        .apexcharts-canvas {
            width: 100% !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .apexcharts-canvas svg {
            width: 100% !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Donut/Pie chart specific */
        .apexcharts-donut,
        .apexcharts-pie {
            width: 100% !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Chart legend positioning - no extra space */
        .apexcharts-legend {
            display: flex !important;
            flex-wrap: wrap !important;
            justify-content: center !important;
            gap: 0.75rem 1.5rem !important;
            padding: 1rem 0.5rem 0.75rem !important;
            margin-top: 1rem !important;
            background: rgba(255, 255, 255, 0.7) !important;
            border-radius: 12px !important;
            border: 1px solid rgba(0, 0, 0, 0.05) !important;
            width: 100% !important;
        }

        /* Remove any potential margins from ApexCharts elements */
        .apexcharts-inner {
            margin: 0 !important;
            padding: 0 !important;
        }

        .apexcharts-series {
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .chart-card {
                min-height: 450px;
                padding: 1.25rem;
            }

            .chart-container {
                min-height: 300px;
            }

            #department-chart,
            #attendance-chart {
                min-height: 300px;
            }

            .chart-header {
                margin-bottom: 0.75rem;
            }
        }

        @media (max-width: 576px) {
            .chart-card {
                min-height: 400px;
                padding: 1rem;
            }

            .chart-container {
                min-height: 280px;
            }

            #department-chart,
            #attendance-chart {
                min-height: 280px;
            }

            .chart-header {
                margin-bottom: 0.5rem;
            }

            .chart-subtitle {
                margin-left: 3rem;
                font-size: 0.8rem;
            }
        }

        /* Type Stats - Improved layout */
        .type-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            flex-shrink: 0;
        }

        .type-stat {
            text-align: center;
            padding: 1rem;
            border-radius: var(--radius-lg);
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.8), rgba(255, 255, 255, 0.6));
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .type-stat:hover {
            transform: translateY(-3px);
            background: white;
            box-shadow: var(--shadow-md);
        }

        .type-stat h4 {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
            line-height: 1.2;
        }

        .type-stat p {
            color: var(--gray-600);
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .type-stat .percentage {
            font-size: 0.75rem;
            color: var(--gray-500);
            padding: 0.25rem 0.75rem;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50px;
            display: inline-block;
            font-weight: 600;
        }

        /* Gender Stats - Improved */
        .gender-stats {
            display: flex;
            gap: 0.75rem;
            margin-top: 0.75rem;
            flex-shrink: 0;
        }

        .gender-stat {
            flex: 1;
            text-align: center;
            padding: 0.75rem 0.5rem;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.8), rgba(255, 255, 255, 0.6));
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .gender-stat.male {
            border-bottom: 3px solid #3b82f6;
        }

        .gender-stat.female {
            border-bottom: 3px solid #ec4899;
        }

        .gender-stat.other {
            border-bottom: 3px solid #8b5cf6;
        }

        .gender-stat h5 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.15rem;
        }

        .gender-stat p {
            color: var(--gray-600);
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* ===== PAYROLL STATS ===== */
        .payroll-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .payroll-stat {
            text-align: center;
            padding: 1.25rem;
            border-radius: var(--radius-lg);
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.8), rgba(255, 255, 255, 0.6));
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .payroll-stat:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .payroll-stat::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .payroll-stat.processed::before {
            background: var(--gradient-success);
        }

        .payroll-stat.pending::before {
            background: var(--gradient-warning);
        }

        .payroll-stat.draft::before {
            background: linear-gradient(135deg, #94a3b8, #64748b);
        }

        .payroll-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin: 0 auto 0.75rem;
            color: white;
        }

        .payroll-stat.processed .payroll-stat-icon {
            background: var(--gradient-success);
        }

        .payroll-stat.pending .payroll-stat-icon {
            background: var(--gradient-warning);
        }

        .payroll-stat.draft .payroll-stat-icon {
            background: linear-gradient(135deg, #94a3b8, #64748b);
        }

        .payroll-stat h4 {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }

        .payroll-stat p {
            color: var(--gray-600);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .payroll-stat small {
            color: var(--gray-500);
            font-size: 0.75rem;
        }

        .payroll-breakdown {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .payroll-breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            font-size: 0.9rem;
        }

        .payroll-breakdown-item:not(:last-child) {
            border-bottom: 1px dashed rgba(0, 0, 0, 0.05);
        }

        .payroll-breakdown-label {
            color: var(--gray-600);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .payroll-breakdown-label i {
            width: 20px;
            color: var(--primary);
        }

        .payroll-breakdown-value {
            font-weight: 700;
            color: var(--dark);
        }

        /* ===== ACTIVITY LIST ===== */
        .activity-list {
            list-style: none;
            max-height: 350px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }

        .activity-list::-webkit-scrollbar {
            width: 6px;
        }

        .activity-list::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 3px;
        }

        .activity-list::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 3px;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: var(--transition);
            border-radius: var(--radius-md);
        }

        .activity-item:hover {
            background: rgba(255, 255, 255, 0.8);
            transform: translateX(5px);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .activity-icon.success {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
        }

        .activity-icon.warning {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
        }

        .activity-icon.primary {
            background: rgba(30, 64, 175, 0.15);
            color: var(--primary);
        }

        .activity-icon.danger {
            background: rgba(239, 68, 68, 0.15);
            color: var(--danger);
        }

        .activity-icon.info {
            background: rgba(59, 130, 246, 0.15);
            color: var(--info);
        }

        .activity-content {
            flex: 1;
        }

        .activity-content h4 {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .activity-content p {
            color: var(--gray-600);
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }

        .activity-user {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .activity-user-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
        }

        .activity-user-name {
            font-size: 0.8rem;
            color: var(--gray-500);
        }

        .activity-time {
            color: var(--gray-500);
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.5rem;
        }

        /* ===== QUICK ACTIONS ===== */
        .quick-actions-section {
            margin-top: 2rem;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-card);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .section-header {
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            width: 40px;
            height: 40px;
            background: var(--gradient-secondary);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }

        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 1rem;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.8);
            border-radius: var(--radius-lg);
            text-decoration: none;
            color: var(--dark);
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .quick-action:hover {
            transform: translateY(-5px);
            background: white;
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }

        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-lg);
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            transition: var(--transition);
        }

        .quick-action:hover .action-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .quick-action:nth-child(2) .action-icon {
            background: var(--gradient-success);
        }

        .quick-action:nth-child(3) .action-icon {
            background: var(--gradient-warning);
        }

        .action-text h4 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .action-text p {
            color: var(--gray-500);
            font-size: 0.8rem;
        }

        /* ===== PROFILE MODAL ===== */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            padding: 1rem;
            backdrop-filter: blur(5px);
        }

        .modal-overlay.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .modal-container {
            background: white;
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
            animation: slideUp 0.4s ease;
        }

        .modal-header {
            padding: 1.5rem;
            background: var(--gradient-primary);
            color: white;
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
            position: relative;
        }

        .modal-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .modal-close {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: var(--transition);
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .profile-picture-section {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .profile-avatar-container {
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-light);
            box-shadow: var(--shadow-md);
        }

        .change-avatar-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .change-avatar-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

        .avatar-input {
            display: none;
        }

        .file-info {
            font-size: 0.8rem;
            color: var(--gray-500);
        }

        .preview-container {
            margin-top: 1rem;
            text-align: center;
        }

        .preview-image {
            max-width: 150px;
            max-height: 150px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
        }

        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .user-info-item {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: var(--radius-md);
            border-left: 4px solid var(--primary);
        }

        .user-info-item label {
            display: block;
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
        }

        .user-info-item span {
            display: block;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--dark);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
            transform: translateY(-2px);
        }

        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--danger);
        }

        /* ===== LOADING OVERLAY ===== */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            backdrop-filter: blur(5px);
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ===== SCROLL TO TOP ===== */
        .scroll-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 50px;
            height: 50px;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.25rem;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            z-index: 998;
        }

        .scroll-top:hover {
            transform: translateY(-5px) scale(1.1);
            box-shadow: var(--shadow-xl);
        }

        .scroll-top.show {
            display: flex;
        }

        /* ===== ANIMATIONS ===== */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1.5rem;
            }

            .sidebar-container {
                transform: translateX(-100%);
            }

            .sidebar-container.active {
                transform: translateX(0);
            }

            .mobile-toggle {
                display: flex;
            }

            .navbar-right .datetime-container {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .type-stats {
                grid-template-columns: 1fr;
            }

            .payroll-stats {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .user-info-grid {
                grid-template-columns: 1fr;
            }

            .modal-container {
                max-width: 95%;
            }

            .scroll-top {
                bottom: 1rem;
                right: 1rem;
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .notification-dropdown {
                min-width: 300px;
                right: -100px;
            }

            .chart-container {
                height: 300px;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 0.75rem;
            }

            .brand-text {
                display: none;
            }

            .chart-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .chart-actions {
                width: 100%;
                justify-content: space-between;
            }

            .chart-action-btn {
                flex: 1;
                text-align: center;
                padding: 0.5rem;
            }

            .activity-item {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .quick-action {
                padding: 1rem;
            }

            .chart-container {
                height: 580px;
            }
        }

        .sidebar-item.logout {
            color: #fecaca;
            margin-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
        }

        .sidebar-item.logout:hover {
            background: rgba(239, 68, 68, 0.15);
            color: #fecaca;
        }

        /* ===== FILTER BUTTONS ===== */
        .filter-buttons {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin: 0 auto 1rem auto;
            padding: 0.25rem;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 50px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            width: fit-content;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }

        .filter-btn {
            background: transparent;
            border: none;
            border-radius: 50px;
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--gray-600);
            cursor: pointer;
            transition: all 0.2s ease;
            letter-spacing: 0.3px;
            white-space: nowrap;
            min-width: 70px;
            text-align: center;
            border: 1px solid transparent;
        }

        .filter-btn:hover {
            background: var(--gray-100);
            color: var(--primary);
            transform: translateY(-1px);
        }

        .filter-btn.active {
            background: white;
            color: var(--primary);
            border-color: var(--primary-light);
            box-shadow: 0 4px 10px rgba(30, 64, 175, 0.15);
            font-weight: 700;
        }

        .filter-btn[data-filter="permanent"].active {
            color: #3b82f6;
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.05);
        }

        .filter-btn[data-filter="contractual"].active {
            color: #f59e0b;
            border-color: #f59e0b;
            background: rgba(245, 158, 11, 0.05);
        }

        .filter-btn[data-filter="joborder"].active {
            color: #10b981;
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.05);
        }

        /* Hover effects for color-specific buttons */
        .filter-btn[data-filter="permanent"]:hover {
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }

        .filter-btn[data-filter="contractual"]:hover {
            color: #f59e0b;
            background: rgba(245, 158, 11, 0.1);
        }

        .filter-btn[data-filter="joborder"]:hover {
            color: #10b981;
            background: rgba(16, 185, 129, 0.1);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .filter-buttons {
                flex-wrap: wrap;
                border-radius: 12px;
                padding: 0.5rem;
                width: 100%;
            }

            .filter-btn {
                flex: 1 1 auto;
                padding: 0.4rem 0.5rem;
                font-size: 0.75rem;
                min-width: 60px;
            }
        }

        @media (max-width: 480px) {
            .filter-buttons {
                flex-direction: column;
                align-items: stretch;
                border-radius: 12px;
            }

            .filter-btn {
                width: 100%;
                padding: 0.6rem;
            }
        }

        /* ===== QUICK STATS ===== */
        .quick-stats {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            padding: 0.5rem;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 30px;
            font-size: 0.8rem;
        }

        .quick-stat-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .quick-stat-item .stat-value {
            font-weight: 700;
            font-size: 0.9rem;
        }

        .quick-stat-item .stat-label {
            font-size: 0.7rem;
            opacity: 0.8;
            color: var(--gray-600);
        }

        .quick-stat-divider {
            color: var(--gray-300);
            font-weight: 600;
        }

        /* Filter wrapper for centering */
        .filter-wrapper {
            display: flex;
            justify-content: center;
            width: 100%;
            margin-bottom: 0.75rem;
        }

        /* Adjust stat card layout */
        #total-employees-card .stat-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        #total-employees-card h3 {
            margin-top: 0.25rem;
        }

        /* Responsive adjustments for quick stats */
        @media (max-width: 480px) {
            .quick-stats {
                flex-wrap: wrap;
                padding: 0.35rem;
            }

            .quick-stat-item {
                flex-direction: column;
                gap: 0;
            }

            .quick-stat-item .stat-label {
                font-size: 0.6rem;
            }
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.35rem;
            margin: 0 0 0.5rem 0;
            padding: 0.25rem;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 10px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            width: 100%;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
        }

        .filter-btn {
            background: transparent;
            border: none;
            border-radius: 6px;
            padding: 0.4rem 0.25rem;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray-600);
            cursor: pointer;
            transition: all 0.2s ease;
            letter-spacing: 0.2px;
            white-space: nowrap;
            text-align: center;
            border: 1px solid transparent;
            width: 100%;
        }

        .filter-btn:hover {
            background: var(--gray-100);
            color: var(--primary);
            transform: translateY(-1px);
        }

        .filter-btn.active {
            background: white;
            color: var(--primary);
            border-color: var(--primary-light);
            box-shadow: 0 2px 6px rgba(30, 64, 175, 0.1);
            font-weight: 700;
        }

        /* Color-specific active states */
        .filter-btn[data-filter="permanent"].active {
            color: #3b82f6;
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.05);
        }

        .filter-btn[data-filter="contractual"].active {
            color: #f59e0b;
            border-color: #f59e0b;
            background: rgba(245, 158, 11, 0.05);
        }

        .filter-btn[data-filter="joborder"].active {
            color: #10b981;
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.05);
        }

        /* Hover effects for color-specific buttons */
        .filter-btn[data-filter="permanent"]:hover {
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }

        .filter-btn[data-filter="contractual"]:hover {
            color: #f59e0b;
            background: rgba(245, 158, 11, 0.1);
        }

        .filter-btn[data-filter="joborder"]:hover {
            color: #10b981;
            background: rgba(16, 185, 129, 0.1);
        }

        /* Position-specific styling */
        .filter-btn:nth-child(1) {
            border-top-left-radius: 6px;
            border-bottom-left-radius: 0;
        }

        .filter-btn:nth-child(2) {
            border-top-right-radius: 6px;
            border-bottom-right-radius: 0;
        }

        .filter-btn:nth-child(3) {
            border-bottom-left-radius: 6px;
            border-top-left-radius: 0;
        }

        .filter-btn:nth-child(4) {
            border-bottom-right-radius: 6px;
            border-top-right-radius: 0;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .filter-grid {
                max-width: 260px;
                gap: 0.4rem;
            }

            .filter-btn {
                padding: 0.5rem 0.4rem;
                font-size: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .filter-grid {
                max-width: 240px;
                gap: 0.3rem;
            }

            .filter-btn {
                padding: 0.5rem 0.3rem;
                font-size: 0.7rem;
            }
        }

        /* Optional: Add a subtle divider between rows */
        .filter-grid::after {
            display: none;
        }

        /* Adjust stat card layout for grid */
        #total-employees-card .stat-content {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            text-align: left;
        }

        #total-employees-card h3 {
            margin: 0.25rem 0 0.15rem;
            font-size: 2rem;
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .stat-card {
                min-height: 260px;
                padding: 1.25rem;
            }

            .stat-icon {
                width: 45px;
                height: 45px;
                font-size: 1.25rem;
            }

            .stat-content h3 {
                font-size: 1.75rem;
            }

            .filter-btn {
                font-size: 0.65rem;
                padding: 0.35rem 0.2rem;
            }
        }

        @media (max-width: 768px) {
            .stat-card {
                min-height: auto;
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                gap: 1rem;
            }

            .stat-card {
                padding: 1.25rem;
                min-height: auto;
            }

            .filter-grid {
                max-width: 280px;
                margin: 0 auto 0.5rem;
            }

            #total-employees-card .stat-content {
                align-items: center;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Navigation Header - UPDATED to match other pages -->
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Left Section -->
            <div class="navbar-left">
                <button class="mobile-toggle" id="sidebar-toggle" aria-label="Toggle menu">
                    <i class="fas fa-bars"></i>
                </button>

                <a href="dashboard.php" class="navbar-brand">
                    <img class="brand-logo"
                        src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                        alt="Paluan LGU Logo">
                    <div class="brand-text">
                        <span class="brand-title">HR Management System</span>
                        <span class="brand-subtitle">Municipality of Paluan</span>
                    </div>
                </a>
            </div>

            <!-- Right Section -->
            <div class="navbar-right">
                <div class="datetime-container">
                    <div class="datetime-box">
                        <i class="datetime-icon fas fa-calendar-alt"></i>
                        <div class="datetime-text">
                            <span class="datetime-label">Date</span>
                            <span class="datetime-value" id="current-date"><?php echo date('F j, Y'); ?></span>
                        </div>
                    </div>
                    <div class="datetime-box">
                        <i class="datetime-icon fas fa-clock"></i>
                        <div class="datetime-text">
                            <span class="datetime-label">Time</span>
                            <span class="datetime-value" id="current-time"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Sidebar - UPDATED to match other pages -->
    <div class="sidebar-container" id="sidebar-container">
        <div class="sidebar">
            <div class="sidebar-content">
                <!-- Dashboard -->
                <a href="#" class="sidebar-item active">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>

                <!-- Employees -->
                <a href="./employees/Employee.php" class="sidebar-item">
                    <i class="fas fa-users"></i>
                    <span>Employees</span>
                </a>

                <!-- Attendance -->
                <a href="attendance.php" class="sidebar-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>

                <!-- Payroll Dropdown -->
                <a href="#" class="sidebar-item" id="payroll-toggle">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payroll</span>
                    <i class="fas fa-chevron-down chevron ml-auto"></i>
                </a>
                <div class="sidebar-dropdown-menu" id="payroll-dropdown">
                    <a href="Payrollmanagement/contractualpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Contractual
                    </a>
                    <a href="Payrollmanagement/joborderpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Job Order
                    </a>
                    <a href="Payrollmanagement/permanentpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Permanent
                    </a>
                </div>

                <!-- Settings -->
                <a href="settings.php" class="sidebar-item">
                    <i class="fas fa-sliders-h"></i>
                    <span>Settings</span>
                </a>

                <!-- Logout -->
                <a href="?logout=true" class="sidebar-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>

            <div class="sidebar-footer">
                <p>HRMS v4.1</p>
                <p style="font-size: 0.7rem;">© <?php echo date('Y'); ?> Paluan LGU</p>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="modal-overlay" id="profile-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h2><i class="fas fa-user-circle"></i> My Profile</h2>
                <button class="modal-close" id="close-profile-modal" aria-label="Close modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" enctype="multipart/form-data" id="profile-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="modal-body">
                    <?php if (isset($_SESSION['profile_update_success'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php
                            echo htmlspecialchars($_SESSION['profile_update_success']);
                            unset($_SESSION['profile_update_success']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['profile_update_error'])): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php
                            echo htmlspecialchars($_SESSION['profile_update_error']);
                            unset($_SESSION['profile_update_error']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <div class="profile-picture-section">
                        <div class="profile-avatar-container">
                            <img src="<?php echo htmlspecialchars(Helpers::getAvatarUrl($adminData['profile_picture'] ?? '', $adminData['full_name'])); ?>"
                                alt="Profile Picture" class="profile-avatar" id="profile-avatar-preview">
                            <button type="button" class="change-avatar-btn" id="change-avatar-btn">
                                <i class="fas fa-camera"></i>
                            </button>
                            <input type="file" name="profile_picture" id="profile_picture" class="avatar-input" accept="image/*">
                        </div>
                        <div class="file-info">
                            <p>Maximum file size: 2MB | Allowed: JPG, PNG, GIF, WEBP</p>
                        </div>
                        <div id="preview-container" class="preview-container" style="display: none;">
                            <img id="image-preview" class="preview-image" alt="Preview">
                        </div>
                    </div>

                    <div class="user-info-grid">
                        <div class="user-info-item">
                            <label>Admin ID</label>
                            <span><?php echo htmlspecialchars($adminData['id']); ?></span>
                        </div>
                        <div class="user-info-item">
                            <label>Role</label>
                            <span><?php echo isset($adminData['is_super_admin']) && $adminData['is_super_admin'] ? 'Super Admin' : 'Administrator'; ?></span>
                        </div>
                        <div class="user-info-item">
                            <label>Last Login</label>
                            <span><?php echo isset($adminData['last_login']) ? date('M j, Y g:i A', strtotime($adminData['last_login'])) : 'Never'; ?></span>
                        </div>
                        <div class="user-info-item">
                            <label>Status</label>
                            <span style="color: var(--success);">Active</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control"
                            value="<?php echo htmlspecialchars($adminData['full_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control"
                            value="<?php echo htmlspecialchars($adminData['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="form-control"
                            value="<?php echo htmlspecialchars($adminData['first_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-control"
                            value="<?php echo htmlspecialchars($adminData['last_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancel-profile-changes">
                        Cancel
                    </button>
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="header-card">
                    <h1 class="dashboard-title">
                        <?php echo Helpers::getGreeting(); ?>, <span><?php echo htmlspecialchars(explode(' ', $adminData['full_name'])[0]); ?></span>
                    </h1>
                    <p class="dashboard-subtitle">
                        Here's what's happening with your HRMS today. Monitor employee metrics, track attendance,
                        and manage payroll efficiently.
                    </p>
                    <div class="welcome-badge">
                        <i class="fas fa-clock"></i>
                        Last login: <?php echo isset($adminData['last_login']) ? date('F j, Y, g:i a', strtotime($adminData['last_login'])) : 'Never'; ?>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <!-- Card 1: Total Employees with Filter -->
                <div class="stat-card" id="total-employees-card">
                    <div class="stat-header">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-trend trend-up" id="employee-trend">
                            <i class="fas fa-arrow-up"></i>
                            <span>35.5%</span>
                        </div>
                    </div>
                    <div class="stat-content">
                        <div class="filter-grid" id="employee-filter">
                            <button class="filter-btn active" data-filter="total">Total</button>
                            <button class="filter-btn" data-filter="permanent">Permanent</button>
                            <button class="filter-btn" data-filter="contractual">Contractual</button>
                            <button class="filter-btn" data-filter="joborder">Job Order</button>
                        </div>
                        <h3 id="employee-count">106</h3>
                        <p class="stat-label" id="employee-label">Total Employees</p>
                        <p class="stat-detail" id="employee-detail">Active workforce across all departments</p>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" id="employee-progress" style="width: 100%"></div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Attendance Rate -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon success">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-trend trend-down">
                            <i class="fas fa-arrow-down"></i>
                            <span>0%</span>
                        </div>
                    </div>
                    <div class="stat-content">
                        <h3>0%</h3>
                        <p class="stat-label">Attendance Rate</p>
                        <p class="stat-detail">0 employees present today</p>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Pending Payroll -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon warning">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-trend trend-down">
                            <i class="fas fa-arrow-down"></i>
                            <span>100%</span>
                        </div>
                    </div>
                    <div class="stat-content">
                        <h3>20</h3>
                        <p class="stat-label">Pending Payroll</p>
                        <p class="stat-detail">Awaiting processing for current month</p>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Grid -->
            <div class="charts-grid">
                <!-- Attendance Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <h3 class="chart-title">
                                <i class="fas fa-chart-line"></i>
                                Attendance Trend
                            </h3>
                            <p class="chart-subtitle">Last 7 days attendance with late count</p>
                        </div>
                        <div class="chart-actions">
                            <button class="chart-action-btn active" data-attendance-range="week">Week</button>
                            <button class="chart-action-btn" data-attendance-range="month">Month</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <div id="attendance-chart"></div>
                    </div>
                    <div class="type-stats">
                        <div class="type-stat">
                            <h4 style="color: var(--primary);"><?php echo $employeeCounts['permanent']; ?></h4>
                            <p>Permanent</p>
                            <div class="percentage"><?php echo Helpers::getPercentage($employeeCounts['permanent'], $employeeCounts['total']); ?>%</div>
                        </div>
                        <div class="type-stat">
                            <h4 style="color: var(--warning);"><?php echo $employeeCounts['contractual']; ?></h4>
                            <p>Contractual</p>
                            <div class="percentage"><?php echo Helpers::getPercentage($employeeCounts['contractual'], $employeeCounts['total']); ?>%</div>
                        </div>
                        <div class="type-stat">
                            <h4 style="color: var(--success);"><?php echo $employeeCounts['job_order']; ?></h4>
                            <p>Job Order</p>
                            <div class="percentage"><?php echo Helpers::getPercentage($employeeCounts['job_order'], $employeeCounts['total']); ?>%</div>
                        </div>
                    </div>
                    <div class="gender-stats">
                        <div class="gender-stat male">
                            <h5><?php echo $genderDistribution['Male']; ?></h5>
                            <p>Male</p>
                        </div>
                        <div class="gender-stat female">
                            <h5><?php echo $genderDistribution['Female']; ?></h5>
                            <p>Female</p>
                        </div>
                        <?php if ($genderDistribution['Other'] > 0): ?>
                            <div class="gender-stat other">
                                <h5><?php echo $genderDistribution['Other']; ?></h5>
                                <p>Other</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Department Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <h3 class="chart-title">
                                <i class="fas fa-sitemap"></i>
                                Department Distribution
                            </h3>
                            <p class="chart-subtitle">Employee breakdown by department</p>
                        </div>
                        <div class="chart-actions">
                            <button class="chart-action-btn active" data-chart-type="donut">Donut</button>
                            <button class="chart-action-btn" data-chart-type="pie">Pie</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <div id="department-chart"></div>
                    </div>
                </div>
            </div>

            <!-- Payroll and Activity Grid -->
            <div class="charts-grid">
                <!-- Payroll Summary -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <h3 class="chart-title">
                                <i class="fas fa-money-bill-wave"></i>
                                Payroll Summary
                            </h3>
                            <p class="chart-subtitle">Current month payroll status</p>
                        </div>
                        <div class="chart-actions">
                            <button class="chart-action-btn active" data-payroll-view="summary">Summary</button>
                            <button class="chart-action-btn" data-payroll-view="breakdown">Breakdown</button>
                        </div>
                    </div>
                    <div id="payroll-summary-view">
                        <div class="payroll-stats">
                            <div class="payroll-stat processed">
                                <div class="payroll-stat-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h4><?php echo $payrollSummary['processed']; ?></h4>
                                <p>Processed</p>
                                <small><?php echo $payrollProcessedPercent; ?>% of total</small>
                            </div>
                            <div class="payroll-stat pending">
                                <div class="payroll-stat-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h4><?php echo $payrollSummary['pending']; ?></h4>
                                <p>Pending</p>
                                <small><?php echo $payrollPendingPercent; ?>% of total</small>
                            </div>
                            <div class="payroll-stat draft">
                                <div class="payroll-stat-icon">
                                    <i class="fas fa-file"></i>
                                </div>
                                <h4><?php echo $payrollSummary['draft']; ?></h4>
                                <p>Draft</p>
                                <small><?php echo Helpers::getPercentage($payrollSummary['draft'], $payrollSummary['total']); ?>% of total</small>
                            </div>
                        </div>
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(0,0,0,0.05);">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--gray-600);">Total Payroll Amount:</span>
                                <span style="font-weight: 700; font-size: 1.25rem; color: var(--primary);">
                                    <?php echo Helpers::formatCurrency($payrollSummary['total_amount']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div id="payroll-breakdown-view" style="display: none;">
                        <div class="payroll-breakdown">
                            <div class="payroll-breakdown-item">
                                <span class="payroll-breakdown-label">
                                    <i class="fas fa-user-tie" style="color: #3b82f6;"></i>
                                    Permanent
                                </span>
                                <span class="payroll-breakdown-value">
                                    <?php echo Helpers::formatCurrency($payrollSummary['by_type']['permanent']['amount'] ?? 0); ?>
                                    <small style="color: var(--gray-500);">
                                        (<?php echo $payrollSummary['by_type']['permanent']['count'] ?? 0; ?> employees)
                                    </small>
                                </span>
                            </div>
                            <div class="payroll-breakdown-item">
                                <span class="payroll-breakdown-label">
                                    <i class="fas fa-file-contract" style="color: #f59e0b;"></i>
                                    Contractual
                                </span>
                                <span class="payroll-breakdown-value">
                                    <?php echo Helpers::formatCurrency($payrollSummary['by_type']['contractual']['amount'] ?? 0); ?>
                                    <small style="color: var(--gray-500);">
                                        (<?php echo $payrollSummary['by_type']['contractual']['count'] ?? 0; ?> employees)
                                    </small>
                                </span>
                            </div>
                            <div class="payroll-breakdown-item">
                                <span class="payroll-breakdown-label">
                                    <i class="fas fa-tasks" style="color: #10b981;"></i>
                                    Job Order
                                </span>
                                <span class="payroll-breakdown-value">
                                    <?php echo Helpers::formatCurrency($payrollSummary['by_type']['job_order']['amount'] ?? 0); ?>
                                    <small style="color: var(--gray-500);">
                                        (<?php echo $payrollSummary['by_type']['job_order']['count'] ?? 0; ?> employees)
                                    </small>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div>
                            <h3 class="chart-title">
                                <i class="fas fa-history"></i>
                                Recent Activity
                            </h3>
                            <p class="chart-subtitle">Latest system updates</p>
                        </div>
                        <button class="chart-action-btn" id="refresh-activities">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <ul class="activity-list" id="activity-list">
                        <?php if (!empty($recentActivities)): ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <li class="activity-item">
                                    <div class="activity-icon <?php echo $activity['icon_color']; ?>">
                                        <i class="fas fa-<?php echo $activity['icon']; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h4><?php echo htmlspecialchars($activity['title']); ?></h4>
                                        <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                        <?php if (!empty($activity['user'])): ?>
                                            <div class="activity-user">
                                                <?php if (!empty($activity['user_avatar'])): ?>
                                                    <img src="<?php echo htmlspecialchars($activity['user_avatar']); ?>"
                                                        alt="<?php echo htmlspecialchars($activity['user']); ?>"
                                                        class="activity-user-avatar">
                                                <?php endif; ?>
                                                <span class="activity-user-name"><?php echo htmlspecialchars($activity['user']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="activity-time">
                                            <i class="far fa-clock"></i>
                                            <?php echo Helpers::timeElapsedString($activity['time']); ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="activity-item">
                                <div class="activity-icon info">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div class="activity-content">
                                    <h4>Welcome to HRMS</h4>
                                    <p>Start managing your HR operations</p>
                                    <div class="activity-time">
                                        <i class="far fa-clock"></i>
                                        just now
                                    </div>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-bolt"></i>
                        Quick Actions
                    </h3>
                </div>
                <div class="quick-actions">
                    <a href="./employees/Employee.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="action-text">
                            <h4>Add Employee</h4>
                            <p>Register new employee</p>
                        </div>
                    </a>
                    <a href="attendance.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="action-text">
                            <h4>Mark Attendance</h4>
                            <p>Record daily attendance</p>
                        </div>
                    </a>
                    <a href="Payrollmanagement/permanentpayrolltable1.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <div class="action-text">
                            <h4>Process Payroll</h4>
                            <p>Generate payroll</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Scroll to Top Button -->
    <button class="scroll-top" id="scrollTop" aria-label="Scroll to top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ===============================================
            // DATE & TIME UPDATE
            // ===============================================
            function updateDateTime() {
                const now = new Date();
                const timeElement = document.getElementById('current-time');

                if (timeElement) {
                    timeElement.textContent = now.toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: true
                    });
                }
            }

            updateDateTime();
            setInterval(updateDateTime, 1000);

            // ===============================================
            // SIDEBAR TOGGLE - UPDATED to match other pages
            // ===============================================
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebarContainer = document.getElementById('sidebar-container');
            const overlay = document.getElementById('overlay');
            const payrollToggle = document.getElementById('payroll-toggle');
            const payrollDropdown = document.getElementById('payroll-dropdown');

            // Ensure payroll dropdown is closed by default on dashboard
            if (payrollToggle && payrollDropdown) {
                // Make sure dropdown is closed and chevron is not rotated
                payrollDropdown.classList.remove('open');
                const chevron = payrollToggle.querySelector('.chevron');
                if (chevron) {
                    chevron.classList.remove('rotated');
                }

                // Toggle functionality
                payrollToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Toggle the dropdown
                    payrollDropdown.classList.toggle('open');

                    // Toggle chevron rotation
                    if (chevron) {
                        chevron.classList.toggle('rotated');
                    }
                });
            }

            // Toggle sidebar
            if (sidebarToggle && sidebarContainer && overlay) {
                sidebarToggle.addEventListener('click', function() {
                    sidebarContainer.classList.toggle('active');
                    overlay.classList.toggle('active');
                    document.body.style.overflow = sidebarContainer.classList.contains('active') ? 'hidden' : '';
                });

                overlay.addEventListener('click', function() {
                    sidebarContainer.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }

            // Close sidebar on window resize if open
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024 && sidebarContainer.classList.contains('active')) {
                    sidebarContainer.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });

            // ===============================================
            // USER DROPDOWN
            // ===============================================
            const userMenuButton = document.getElementById('user-menu-button');
            const userDropdown = document.getElementById('user-dropdown');

            if (userMenuButton && userDropdown) {
                userMenuButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle('active');
                    this.setAttribute('aria-expanded', userDropdown.classList.contains('active'));
                });

                document.addEventListener('click', function(e) {
                    if (!userMenuButton.contains(e.target) && !userDropdown.contains(e.target)) {
                        userDropdown.classList.remove('active');
                        userMenuButton.setAttribute('aria-expanded', false);
                    }
                });
            }

            // ===============================================
            // NOTIFICATION DROPDOWN
            // ===============================================
            const notificationButton = document.getElementById('notification-button');
            const notificationDropdown = document.getElementById('notification-dropdown');

            if (notificationButton && notificationDropdown) {
                notificationButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('active');

                    // Mark as viewed
                    const badge = document.getElementById('notification-badge');
                    if (badge) {
                        badge.style.display = 'none';
                    }
                });

                document.addEventListener('click', function(e) {
                    if (!notificationButton.contains(e.target) && !notificationDropdown.contains(e.target)) {
                        notificationDropdown.classList.remove('active');
                    }
                });

                // Mark all as read
                document.getElementById('mark-all-read')?.addEventListener('click', function(e) {
                    e.preventDefault();

                    const items = document.querySelectorAll('.notification-item.unread');
                    items.forEach(item => item.classList.remove('unread'));

                    Swal.fire({
                        icon: 'success',
                        title: 'Marked as Read',
                        text: 'All notifications marked as read',
                        timer: 1500,
                        showConfirmButton: false
                    });
                });
            }

            // ===============================================
            // PROFILE MODAL
            // ===============================================
            const profileModal = document.getElementById('profile-modal');
            const openProfileModal = document.getElementById('open-profile-modal');
            const closeProfileModal = document.getElementById('close-profile-modal');
            const cancelProfileChanges = document.getElementById('cancel-profile-changes');

            function openModal() {
                profileModal.classList.add('active');
                document.body.style.overflow = 'hidden';
                userDropdown.classList.remove('active');
            }

            function closeModal() {
                profileModal.classList.remove('active');
                document.body.style.overflow = '';
            }

            if (openProfileModal) {
                openProfileModal.addEventListener('click', function(e) {
                    e.preventDefault();
                    openModal();
                });
            }

            if (closeProfileModal) {
                closeProfileModal.addEventListener('click', closeModal);
            }

            if (cancelProfileChanges) {
                cancelProfileChanges.addEventListener('click', closeModal);
            }

            if (profileModal) {
                profileModal.addEventListener('click', function(e) {
                    if (e.target === profileModal) {
                        closeModal();
                    }
                });
            }

            // ===============================================
            // PROFILE PICTURE UPLOAD
            // ===============================================
            const changeAvatarBtn = document.getElementById('change-avatar-btn');
            const profilePictureInput = document.getElementById('profile_picture');
            const profileAvatarPreview = document.getElementById('profile-avatar-preview');
            const previewContainer = document.getElementById('preview-container');
            const imagePreview = document.getElementById('image-preview');

            if (changeAvatarBtn && profilePictureInput) {
                changeAvatarBtn.addEventListener('click', function() {
                    profilePictureInput.click();
                });

                profilePictureInput.addEventListener('change', function(e) {
                    if (e.target.files && e.target.files[0]) {
                        const file = e.target.files[0];

                        // Validate file size
                        if (file.size > 2 * 1024 * 1024) {
                            Swal.fire({
                                icon: 'error',
                                title: 'File Too Large',
                                text: 'Maximum file size is 2MB'
                            });
                            return;
                        }

                        // Validate file type
                        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                        if (!validTypes.includes(file.type)) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Invalid File Type',
                                text: 'Please upload JPG, PNG, GIF, or WEBP images only'
                            });
                            return;
                        }

                        const reader = new FileReader();

                        reader.onload = function(event) {
                            if (profileAvatarPreview) {
                                profileAvatarPreview.src = event.target.result;
                            }
                            if (previewContainer && imagePreview) {
                                previewContainer.style.display = 'block';
                                imagePreview.src = event.target.result;
                            }
                        };

                        reader.readAsDataURL(file);
                    }
                });
            }

            // ===============================================
            // LOGOUT CONFIRMATION
            // ===============================================
            const logoutBtn = document.getElementById('logout-btn');

            if (logoutBtn) {
                logoutBtn.addEventListener('click', function(e) {
                    e.preventDefault();

                    Swal.fire({
                        title: 'Are you sure?',
                        text: "You will be logged out of the system",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#1e40af',
                        cancelButtonColor: '#ef4444',
                        confirmButtonText: 'Yes, logout',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = '?logout=true';
                        }
                    });
                });
            }

            // ===============================================
            // SCROLL TO TOP
            // ===============================================
            const scrollTopBtn = document.getElementById('scrollTop');

            if (scrollTopBtn) {
                window.addEventListener('scroll', function() {
                    if (window.pageYOffset > 300) {
                        scrollTopBtn.classList.add('show');
                    } else {
                        scrollTopBtn.classList.remove('show');
                    }
                });

                scrollTopBtn.addEventListener('click', function() {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }

            // ===============================================
            // CHART DATA
            // ===============================================

            // Department chart data
            const deptSeries = <?php echo $deptSeriesJson ?: '[]'; ?>;
            const deptLabels = <?php echo $deptLabelsJson ?: '[]'; ?>;
            const deptColors = <?php echo $deptColorsJson ?: '[]'; ?>;

            // Ensure we have valid data
            if (!Array.isArray(deptSeries) || deptSeries.length === 0) {
                deptSeries = [1];
                deptLabels = ['No Data'];
                deptColors = ['#94a3b8'];
            }

            // Attendance chart data
            const attendancePresent = <?php echo $attendancePresentJson ?: '[0]'; ?>;
            const attendanceLate = <?php echo $attendanceLateJson ?: '[0]'; ?>;
            const attendanceLabels = <?php echo $attendanceLabelsJson ?: '[]'; ?>;

            // ===============================================
            // DEPARTMENT CHART - FIXED: Fill container completely
            // ===============================================
            let departmentChart;

            function createDepartmentChart(type = 'donut') {
                const chartContainer = document.querySelector("#department-chart");
                const parentContainer = document.querySelector(".chart-container");

                if (!chartContainer || !parentContainer) {
                    console.error('Department chart container not found');
                    return;
                }

                // Destroy existing chart
                if (departmentChart) {
                    departmentChart.destroy();
                }

                // Clear and ensure container takes full height
                chartContainer.innerHTML = '';
                chartContainer.style.height = '100%';
                chartContainer.style.width = '100%';
                chartContainer.style.minHeight = parentContainer.style.minHeight || '350px';

                // FIXED: Base options with proper sizing
                const baseOptions = {
                    series: deptSeries,
                    chart: {
                        type: type,
                        height: '100%',
                        width: '100%',
                        toolbar: {
                            show: false
                        },
                        animations: {
                            enabled: true,
                            easing: 'easeinout',
                            speed: 800
                        },
                        fontFamily: 'Inter, sans-serif',
                        redrawOnWindowResize: true,
                        redrawOnParentResize: true,
                        parentHeightOffset: 0
                    },
                    labels: deptLabels,
                    colors: deptColors,
                    legend: {
                        show: true,
                        position: 'bottom',
                        fontSize: '12px',
                        markers: {
                            width: 12,
                            height: 12,
                            radius: 6
                        },
                        itemMargin: {
                            horizontal: 10,
                            vertical: 5
                        },
                        formatter: function(seriesName, opts) {
                            const val = opts.w.globals.series[opts.seriesIndex];
                            const total = opts.w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                            return seriesName + ': ' + val + ' (' + percentage + '%)';
                        },
                        offsetY: 5
                    },
                    tooltip: {
                        y: {
                            formatter: (val) => val + ' employees'
                        }
                    },
                    // FIXED: Responsive settings
                    responsive: [{
                        breakpoint: 1024,
                        options: {
                            legend: {
                                fontSize: '11px',
                                position: 'bottom',
                                offsetY: 10
                            }
                        }
                    }, {
                        breakpoint: 768,
                        options: {
                            legend: {
                                fontSize: '10px',
                                position: 'bottom',
                                markers: {
                                    width: 8,
                                    height: 8
                                },
                                itemMargin: {
                                    horizontal: 5,
                                    vertical: 3
                                }
                            }
                        }
                    }, {
                        breakpoint: 480,
                        options: {
                            legend: {
                                fontSize: '9px',
                                position: 'bottom',
                                markers: {
                                    width: 6,
                                    height: 6
                                },
                                itemMargin: {
                                    horizontal: 3,
                                    vertical: 2
                                }
                            }
                        }
                    }]
                };

                // Type-specific options
                let typeOptions = {};

                if (type === 'donut') {
                    typeOptions = {
                        chart: {
                            type: 'donut'
                        },
                        plotOptions: {
                            pie: {
                                donut: {
                                    size: '65%',
                                    labels: {
                                        show: true,
                                        name: {
                                            show: true,
                                            fontSize: '14px',
                                            fontWeight: 600,
                                            offsetY: -10
                                        },
                                        value: {
                                            show: true,
                                            fontSize: '16px',
                                            fontWeight: 700,
                                            formatter: (val) => val + ' employees',
                                            offsetY: 5
                                        },
                                        total: {
                                            show: true,
                                            label: 'Total',
                                            fontSize: '13px',
                                            fontWeight: 600,
                                            formatter: (w) => {
                                                const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                                return total + ' employees';
                                            }
                                        }
                                    }
                                },
                                expandOnClick: false
                            }
                        },
                        dataLabels: {
                            enabled: true,
                            style: {
                                fontSize: '11px',
                                fontWeight: 600,
                                colors: ['#fff']
                            },
                            formatter: (val, opts) => {
                                return val.toFixed(1) + '%';
                            },
                            dropShadow: {
                                enabled: false
                            }
                        },
                        stroke: {
                            show: true,
                            width: 2,
                            colors: ['#fff']
                        }
                    };
                } else if (type === 'pie') {
                    typeOptions = {
                        chart: {
                            type: 'pie'
                        },
                        plotOptions: {
                            pie: {
                                expandOnClick: false
                            }
                        },
                        dataLabels: {
                            enabled: true,
                            style: {
                                fontSize: '11px',
                                fontWeight: 600,
                                colors: ['#fff']
                            },
                            formatter: (val, opts) => {
                                const label = opts.w.globals.labels[opts.seriesIndex];
                                return label + ': ' + val.toFixed(1) + '%';
                            },
                            dropShadow: {
                                enabled: false
                            }
                        },
                        stroke: {
                            show: true,
                            width: 2,
                            colors: ['#fff']
                        }
                    };
                }

                // Merge options
                const options = {
                    ...baseOptions,
                    ...typeOptions
                };

                try {
                    departmentChart = new ApexCharts(chartContainer, options);
                    departmentChart.render();

                    // Force resize after render to ensure full height
                    setTimeout(() => {
                        if (departmentChart) {
                            departmentChart.updateOptions({
                                chart: {
                                    height: '100%',
                                    width: '100%'
                                }
                            }, false, true, true);
                        }
                    }, 100);
                } catch (error) {
                    console.error('Chart error:', error);
                    chartContainer.innerHTML = '<div style="text-align: center; padding: 2rem; color: #ef4444;">Error loading chart</div>';
                }
            }
            // ===============================================
            // ATTENDANCE CHART - FIXED: Better sizing
            // ===============================================
            let attendanceChart;

            function createAttendanceChart(range = 'week') {
                const chartContainer = document.querySelector("#attendance-chart");

                if (!chartContainer) {
                    console.error('Attendance chart container not found');
                    return;
                }

                // Destroy existing chart
                if (attendanceChart) {
                    attendanceChart.destroy();
                }

                // Clear and set dimensions
                chartContainer.innerHTML = '';
                chartContainer.style.height = '350px';
                chartContainer.style.width = '100%';

                let series = [];
                let categories = [];

                if (range === 'week') {
                    series = [{
                            name: 'Present',
                            data: attendancePresent
                        },
                        {
                            name: 'Late',
                            data: attendanceLate
                        }
                    ];
                    categories = attendanceLabels;
                } else {
                    // Generate month data
                    const monthDates = [];
                    const monthPresent = [];
                    const monthLate = [];

                    for (let i = 29; i >= 0; i--) {
                        const date = new Date();
                        date.setDate(date.getDate() - i);
                        monthDates.push(date.toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric'
                        }));

                        const basePresent = attendancePresent[attendancePresent.length - 1] || 85;
                        monthPresent.push(basePresent + (Math.floor(Math.random() * 10) - 3));

                        const baseLate = attendanceLate[attendanceLate.length - 1] || 5;
                        monthLate.push(Math.max(0, baseLate + (Math.floor(Math.random() * 4) - 2)));
                    }

                    series = [{
                            name: 'Present',
                            data: monthPresent
                        },
                        {
                            name: 'Late',
                            data: monthLate
                        }
                    ];
                    categories = monthDates;
                }

                const options = {
                    series: series,
                    chart: {
                        type: 'area',
                        height: '100%',
                        width: '100%',
                        toolbar: {
                            show: false
                        },
                        animations: {
                            enabled: true,
                            easing: 'easeinout',
                            speed: 800
                        },
                        fontFamily: 'Inter, sans-serif',
                        // FIXED: Ensure proper redraw
                        redrawOnWindowResize: true,
                        redrawOnParentResize: true
                    },
                    colors: ['#3b82f6', '#f59e0b'],
                    dataLabels: {
                        enabled: false
                    },
                    stroke: {
                        width: [3, 2],
                        curve: 'smooth',
                        dashArray: [0, 5]
                    },
                    fill: {
                        type: ['gradient', 'solid'],
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.7,
                            opacityTo: 0.2
                        }
                    },
                    markers: {
                        size: 4,
                        colors: ['#fff', '#fff'],
                        strokeColors: ['#3b82f6', '#f59e0b'],
                        strokeWidth: 2
                    },
                    xaxis: {
                        categories: categories,
                        labels: {
                            style: {
                                colors: '#6b7280',
                                fontSize: '11px'
                            },
                            rotate: range === 'month' ? -45 : 0
                        }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                colors: '#6b7280',
                                fontSize: '11px'
                            },
                            formatter: (val) => Math.round(val)
                        },
                        title: {
                            text: 'Employees',
                            style: {
                                fontSize: '11px',
                                color: '#6b7280'
                            }
                        }
                    },
                    grid: {
                        borderColor: '#e5e7eb',
                        strokeDashArray: 4
                    },
                    tooltip: {
                        shared: true,
                        y: {
                            formatter: (val) => val + ' employees'
                        }
                    },
                    legend: {
                        position: 'top',
                        fontSize: '12px',
                        markers: {
                            width: 8,
                            height: 8,
                            radius: 4
                        }
                    },
                    // FIXED: Responsive settings
                    responsive: [{
                        breakpoint: 768,
                        options: {
                            xaxis: {
                                labels: {
                                    rotate: -45,
                                    rotateAlways: true
                                }
                            }
                        }
                    }]
                };

                try {
                    attendanceChart = new ApexCharts(chartContainer, options);
                    attendanceChart.render();
                } catch (error) {
                    console.error('Attendance chart error:', error);
                    chartContainer.innerHTML = '<div style="text-align: center; padding: 2rem; color: #ef4444;">Error loading chart</div>';
                }
            }

            // Initialize charts with slight delay
            setTimeout(() => {
                createDepartmentChart('donut');
                createAttendanceChart('week');
            }, 100);

            // ===============================================
            // CHART TYPE TOGGLE - DEPARTMENT
            // ===============================================
            const chartTypeButtons = document.querySelectorAll('[data-chart-type]');

            chartTypeButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();

                    chartTypeButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    const type = this.getAttribute('data-chart-type');
                    const container = document.querySelector("#department-chart");

                    container.style.opacity = '0.5';
                    setTimeout(() => {
                        createDepartmentChart(type);
                        container.style.opacity = '1';
                    }, 200);
                });
            });

            // ===============================================
            // ATTENDANCE RANGE TOGGLE
            // ===============================================
            const attendanceRangeButtons = document.querySelectorAll('[data-attendance-range]');

            attendanceRangeButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();

                    attendanceRangeButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    const range = this.getAttribute('data-attendance-range');
                    const container = document.querySelector("#attendance-chart");

                    container.style.opacity = '0.5';
                    setTimeout(() => {
                        createAttendanceChart(range);
                        container.style.opacity = '1';
                    }, 200);
                });
            });

            // ===============================================
            // PAYROLL VIEW TOGGLE
            // ===============================================
            const summaryView = document.getElementById('payroll-summary-view');
            const breakdownView = document.getElementById('payroll-breakdown-view');

            if (summaryView && breakdownView) {
                document.querySelectorAll('[data-payroll-view]').forEach(button => {
                    button.addEventListener('click', function() {
                        document.querySelectorAll('[data-payroll-view]').forEach(btn => {
                            btn.classList.remove('active');
                        });
                        this.classList.add('active');

                        const view = this.getAttribute('data-payroll-view');
                        summaryView.style.display = view === 'summary' ? 'block' : 'none';
                        breakdownView.style.display = view === 'breakdown' ? 'block' : 'none';
                    });
                });
            }

            // ===============================================
            // REFRESH ACTIVITIES
            // ===============================================
            const refreshBtn = document.getElementById('refresh-activities');

            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    const icon = this.querySelector('i');
                    icon.classList.add('fa-spin');

                    setTimeout(() => {
                        icon.classList.remove('fa-spin');
                        Swal.fire({
                            icon: 'success',
                            title: 'Activities Updated',
                            text: 'Latest activities loaded',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }, 1500);
                });
            }

            // ===============================================
            // WINDOW RESIZE HANDLER - FIXED: Better resize handling
            // ===============================================
            let resizeTimeout;
            let lastWidth = window.innerWidth;

            window.addEventListener('resize', function() {
                // Only redraw if width actually changed (not height only)
                const currentWidth = window.innerWidth;
                if (Math.abs(currentWidth - lastWidth) > 50) {
                    clearTimeout(resizeTimeout);
                    resizeTimeout = setTimeout(() => {
                        if (departmentChart) {
                            departmentChart.updateOptions({
                                chart: {
                                    height: '100%',
                                    width: '100%'
                                }
                            }, false, true, true);
                        }
                        if (attendanceChart) {
                            attendanceChart.updateOptions({
                                chart: {
                                    height: '100%',
                                    width: '100%'
                                }
                            }, false, true, true);
                        }
                        lastWidth = currentWidth;
                    }, 250);
                }
            });

            // ===============================================
            // AUTO-REFRESH NOTIFICATIONS
            // ===============================================
            setInterval(() => {
                const badge = document.getElementById('notification-badge');
                if (badge) {
                    const currentCount = parseInt(badge.textContent) || 0;
                    const newCount = Math.floor(Math.random() * 3);
                    if (newCount > 0) {
                        badge.textContent = currentCount + newCount;
                        badge.style.display = 'flex';
                    }
                }
            }, 30000);

            // ===============================================
            // ESCAPE KEY HANDLER
            // ===============================================
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (profileModal?.classList.contains('active')) closeModal();
                    if (userDropdown?.classList.contains('active')) {
                        userDropdown.classList.remove('active');
                        userMenuButton?.setAttribute('aria-expanded', false);
                    }
                    if (notificationDropdown?.classList.contains('active')) {
                        notificationDropdown.classList.remove('active');
                    }
                    if (sidebarContainer?.classList.contains('active')) {
                        sidebarContainer.classList.remove('active');
                        overlay?.classList.remove('active');
                    }
                }
            });

            // ===============================================
            // LOADING OVERLAY
            // ===============================================
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.classList.add('active');
                setTimeout(() => loadingOverlay.classList.remove('active'), 800);
            }

            // ===============================================
            // KEYBOARD SHORTCUTS
            // ===============================================
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'd') {
                    e.preventDefault();
                    window.location.href = 'dashboard.php';
                }
                if (e.ctrlKey && e.key === 'e') {
                    e.preventDefault();
                    window.location.href = './employees/Employee.php';
                }
                if (e.ctrlKey && e.key === 'a') {
                    e.preventDefault();
                    window.location.href = 'attendance.php';
                }
                if (e.ctrlKey && e.key === 'p') {
                    e.preventDefault();
                    window.location.href = 'Payrollmanagement/permanentpayrolltable1.php';
                }
                if (e.key === '?' && !e.ctrlKey && !e.altKey && !e.metaKey) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Keyboard Shortcuts',
                        html: `
                            <div style="text-align: left;">
                                <p><strong>Ctrl + D</strong> - Dashboard</p>
                                <p><strong>Ctrl + E</strong> - Employees</p>
                                <p><strong>Ctrl + A</strong> - Attendance</p>
                                <p><strong>Ctrl + P</strong> - Payroll</p>
                                <p><strong>Esc</strong> - Close modals</p>
                                <p><strong>?</strong> - Show this help</p>
                            </div>
                        `,
                        icon: 'info',
                        confirmButtonColor: '#1e40af'
                    });
                }
            });

            // ===============================================
            // EMPLOYEE FILTER FUNCTIONALITY
            // ===============================================
            const employeeData = {
                total: {
                    count: <?php echo $employeeCounts['total']; ?>,
                    label: 'Total Employees',
                    detail: 'Active workforce across all departments',
                    progress: 100,
                    trend: '12%',
                    trendIcon: 'arrow-up',
                    trendClass: 'trend-up'
                },
                permanent: {
                    count: <?php echo $employeeCounts['permanent']; ?>,
                    label: 'Permanent Employees',
                    detail: 'Regular full-time employees with tenure',
                    progress: <?php echo Helpers::getPercentage($employeeCounts['permanent'], $employeeCounts['total']); ?>,
                    trend: '<?php echo Helpers::getPercentage($employeeCounts['permanent'], $employeeCounts['total']); ?>%',
                    trendIcon: <?php echo $employeeCounts['permanent'] > 0 ? "'arrow-up'" : "'minus'"; ?>,
                    trendClass: <?php echo $employeeCounts['permanent'] > 0 ? "'trend-up'" : "''"; ?>
                },
                contractual: {
                    count: <?php echo $employeeCounts['contractual']; ?>,
                    label: 'Contractual Employees',
                    detail: 'Contract-based employees with fixed terms',
                    progress: <?php echo Helpers::getPercentage($employeeCounts['contractual'], $employeeCounts['total']); ?>,
                    trend: '<?php echo Helpers::getPercentage($employeeCounts['contractual'], $employeeCounts['total']); ?>%',
                    trendIcon: <?php echo $employeeCounts['contractual'] > 0 ? "'arrow-up'" : "'minus'"; ?>,
                    trendClass: <?php echo $employeeCounts['contractual'] > 0 ? "'trend-up'" : "''"; ?>
                },
                joborder: {
                    count: <?php echo $employeeCounts['job_order']; ?>,
                    label: 'Job Order Employees',
                    detail: 'Project-based and temporary personnel',
                    progress: <?php echo Helpers::getPercentage($employeeCounts['job_order'], $employeeCounts['total']); ?>,
                    trend: '<?php echo Helpers::getPercentage($employeeCounts['job_order'], $employeeCounts['total']); ?>%',
                    trendIcon: <?php echo $employeeCounts['job_order'] > 0 ? "'arrow-up'" : "'minus'"; ?>,
                    trendClass: <?php echo $employeeCounts['job_order'] > 0 ? "'trend-up'" : "''"; ?>
                }
            };

            // Get DOM elements
            const filterButtons = document.querySelectorAll('.filter-btn');
            const employeeCount = document.getElementById('employee-count');
            const employeeLabel = document.getElementById('employee-label');
            const employeeDetail = document.getElementById('employee-detail');
            const employeeProgress = document.getElementById('employee-progress');
            const employeeTrend = document.getElementById('employee-trend');

            // Add click event to filter buttons
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterButtons.forEach(btn => btn.classList.remove('active'));

                    // Add active class to clicked button
                    this.classList.add('active');

                    // Get filter value
                    const filter = this.getAttribute('data-filter');

                    // Update card with filtered data
                    updateEmployeeCard(filter);
                });
            });

            // Function to update employee card based on filter
            function updateEmployeeCard(filter) {
                const data = employeeData[filter];

                if (!data) return;

                // Update count with animation
                animateNumber(employeeCount, parseInt(employeeCount.textContent), data.count, 500);

                // Update label and detail
                employeeLabel.textContent = data.label;
                employeeDetail.textContent = data.detail;

                // Update progress bar
                employeeProgress.style.width = data.progress + '%';

                // Update trend
                const trendIcon = employeeTrend.querySelector('i');
                const trendSpan = employeeTrend.querySelector('span');

                if (trendIcon) {
                    trendIcon.className = `fas fa-${data.trendIcon}`;
                }

                if (trendSpan) {
                    trendSpan.textContent = data.trend;
                }

                // Update trend class
                employeeTrend.className = `stat-trend ${data.trendClass}`;

                // Update progress bar color based on filter
                const statCard = document.getElementById('total-employees-card');
                const progressBar = document.getElementById('employee-progress');

                // Remove all color classes
                statCard.classList.remove('primary', 'success', 'warning', 'info');
                progressBar.classList.remove('bg-gradient-primary', 'bg-gradient-success', 'bg-gradient-warning', 'bg-gradient-info');

                // Add appropriate color based on filter
                switch (filter) {
                    case 'permanent':
                        statCard.classList.add('primary');
                        progressBar.style.background = 'linear-gradient(135deg, #3b82f6 0%, #1e40af 100%)';
                        break;
                    case 'contractual':
                        statCard.classList.add('warning');
                        progressBar.style.background = 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)';
                        break;
                    case 'joborder':
                        statCard.classList.add('success');
                        progressBar.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                        break;
                    default:
                        statCard.classList.add('primary');
                        progressBar.style.background = 'linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%)';
                }
            }

            // Number animation function
            function animateNumber(element, start, end, duration) {
                const range = end - start;
                const increment = range / (duration / 10);
                let current = start;

                const timer = setInterval(() => {
                    current += increment;

                    if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                        clearInterval(timer);
                        element.textContent = Math.round(end);
                    } else {
                        element.textContent = Math.round(current);
                    }
                }, 10);
            }

            // Optional: Add keyboard shortcuts for filters
            document.addEventListener('keydown', function(e) {
                if (e.altKey) {
                    switch (e.key) {
                        case '1':
                            document.querySelector('[data-filter="total"]').click();
                            e.preventDefault();
                            break;
                        case '2':
                            document.querySelector('[data-filter="permanent"]').click();
                            e.preventDefault();
                            break;
                        case '3':
                            document.querySelector('[data-filter="contractual"]').click();
                            e.preventDefault();
                            break;
                        case '4':
                            document.querySelector('[data-filter="joborder"]').click();
                            e.preventDefault();
                            break;
                    }
                }
            });

            // Add tooltips to filter buttons
            filterButtons.forEach(button => {
                const filter = button.getAttribute('data-filter');
                let tooltipText = '';

                switch (filter) {
                    case 'total':
                        tooltipText = 'Show all employees (Alt+1)';
                        break;
                    case 'permanent':
                        tooltipText = 'Show permanent employees only (Alt+2)';
                        break;
                    case 'contractual':
                        tooltipText = 'Show contractual employees only (Alt+3)';
                        break;
                    case 'joborder':
                        tooltipText = 'Show job order employees only (Alt+4)';
                        break;
                }

                button.setAttribute('title', tooltipText);
            });
        });
    </script>
</body>

</html>