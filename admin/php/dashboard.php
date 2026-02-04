<?php
// Set session security headers BEFORE session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

// Start session
session_start();

// Regenerate session ID periodically for security
if (!isset($_SESSION['last_regeneration'])) {
    if (function_exists('session_regenerate_id')) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
    if (function_exists('session_regenerate_id')) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Check if user is logged in - with proper validation
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Store current URL for redirect back after login
    if (isset($_SERVER['REQUEST_URI'])) {
        $_SESSION['redirect_url'] = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);
    }

    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Database connection configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "hrms_paluan";

// First, fetch user data from database to ensure it's current
try {
    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");

    // Get user ID from session
    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 1;

    // Fetch current user data from admin table
    $sql = "SELECT * FROM admin WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();

        // Update session variables with fresh data from database
        $_SESSION['user_name'] = $user_data['full_name'];
        $_SESSION['user_email'] = $user_data['email'];
        $_SESSION['user_role'] = $user_data['user_role'];
        $_SESSION['user_avatar'] = !empty($user_data['profile_picture']) ? $user_data['profile_picture'] : './img/admin1.png';
        $_SESSION['last_login'] = $user_data['last_login'];

        // If login_time is not set or we want to use last_login from DB
        if (!isset($_SESSION['login_time']) && !empty($user_data['last_login'])) {
            $_SESSION['login_time'] = strtotime($user_data['last_login']);
        }
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    // Continue with session data if DB fetch fails
}

// Validate required session variables with fallbacks
$user_name = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') : 'Administrator';
$user_email = isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email'], ENT_QUOTES, 'UTF-8') : 'admin@paluan.gov.ph';
$login_time = isset($_SESSION['login_time']) ? (int) $_SESSION['login_time'] : time();
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 1;
$user_role = isset($_SESSION['user_role']) ? htmlspecialchars($_SESSION['user_role'], ENT_QUOTES, 'UTF-8') : 'Administrator';
$user_avatar = isset($_SESSION['user_avatar']) ? htmlspecialchars($_SESSION['user_avatar'], ENT_QUOTES, 'UTF-8') : './img/admin1.png';

// Handle profile picture upload and profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Database connection for profile update
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "hrms_paluan";

    try {
        $conn = new mysqli($host, $username, $password, $database);

        if ($conn->connect_error) {
            throw new Exception("Database connection failed: " . $conn->connect_error);
        }

        $conn->set_charset("utf8mb4");

        // Handle file upload
        $upload_success = false;
        $new_avatar_path = $user_avatar;

        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));

            if (in_array($file_extension, $allowed_extensions)) {
                $max_size = 2 * 1024 * 1024; // 2MB

                if ($_FILES['profile_picture']['size'] <= $max_size) {
                    $upload_dir = './img/uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $new_filename = 'admin_' . $user_id . '_' . time() . '.' . $file_extension;
                    $target_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
                        $new_avatar_path = './img/uploads/' . $new_filename;
                        $upload_success = true;

                        // Delete old avatar if it's not the default one
                        if ($user_avatar !== './img/admin1.png' && $user_avatar !== $new_avatar_path && file_exists($user_avatar)) {
                            unlink($user_avatar);
                        }
                    }
                }
            }
        }

        // Update admin information in database
        $new_name = isset($_POST['full_name']) ? $conn->real_escape_string(trim($_POST['full_name'])) : $user_name;
        $new_email = isset($_POST['email']) ? $conn->real_escape_string(trim($_POST['email'])) : $user_email;

        // Prepare SQL based on whether we have a new avatar
        if ($upload_success) {
            $sql = "UPDATE admin SET 
                    full_name = ?, 
                    email = ?,
                    profile_picture = ?,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $new_name, $new_email, $new_avatar_path, $user_id);
        } else {
            $sql = "UPDATE admin SET 
                    full_name = ?, 
                    email = ?,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $new_name, $new_email, $user_id);
        }

        if ($stmt->execute()) {
            // Update session variables
            $_SESSION['user_name'] = $new_name;
            $_SESSION['user_email'] = $new_email;
            if ($upload_success) {
                $_SESSION['user_avatar'] = $new_avatar_path;
            }

            $_SESSION['profile_update_success'] = 'Profile updated successfully!';
        } else {
            $_SESSION['profile_update_error'] = 'Failed to update profile. Database error: ' . $stmt->error;
        }

        $stmt->close();
        $conn->close();

        // Refresh the page to show updated information
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        $_SESSION['profile_update_error'] = 'An error occurred while updating your profile: ' . $e->getMessage();
    }
}

// Logout functionality
if (isset($_GET['logout'])) {
    // Update last_login time before logging out
    try {
        $conn = new mysqli($host, $username, $password, $database);

        if (!$conn->connect_error) {
            $conn->set_charset("utf8mb4");

            $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
            if ($user_id > 0) {
                $sql = "UPDATE admin SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
            }

            $conn->close();
        }
    } catch (Exception $e) {
        error_log("Error updating last login: " . $e->getMessage());
    }

    // Clear all session variables
    $_SESSION = array();

    // Destroy the session cookie
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

    // Destroy the session
    session_destroy();

    // Clear remember me cookie if exists
    if (isset($_COOKIE['remember_user'])) {
        setcookie('remember_user', '', time() - 3600, "/", "", true, true);
    }

    // Redirect to login page
    header('Location: login.php');
    exit();
}

// ===============================================
// DASHBOARD DATA FETCHING
// ===============================================
// Table mapping
$tables = [
    'PERMANENT' => 'permanent',
    'Contractual' => 'contractofservice',
    'Job Order' => 'job_order'
];

// Initialize variables with default values
$perm_employees_count = 0;
$cont_employees_count = 0;
$jo_employees_count = 0;
$total_employees = 0;
$department_data = [];
$department_breakdown = [];

// For attendance line chart
$attendance_dates = [];
$attendance_counts = [];
$attendance_series = "[]";
$attendance_labels = "[]";

// For payroll data
$payroll_processed = 0;
$payroll_pending = 0;
$payroll_total = 0;

// For recent activity
$recent_activities = [];

// For chart data
$dept_series = "[]";
$dept_labels = "[]";
$dept_colors_json = "[]";

// Color palette for departments
$color_palette = [
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

// Establish connection for dashboard data
try {
    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Set charset to UTF-8
    $conn->set_charset("utf8mb4");

    // Count employees from each table
    $sql_perm = "SELECT COUNT(*) as count FROM permanent";
    $result_perm = $conn->query($sql_perm);
    if ($result_perm && $result_perm->num_rows > 0) {
        $row = $result_perm->fetch_assoc();
        $perm_employees_count = (int) $row['count'];
    }

    $sql_cont = "SELECT COUNT(*) as count FROM contractofservice";
    $result_cont = $conn->query($sql_cont);
    if ($result_cont && $result_cont->num_rows > 0) {
        $row = $result_cont->fetch_assoc();
        $cont_employees_count = (int) $row['count'];
    }

    $sql_jo = "SELECT COUNT(*) as count FROM job_order";
    $result_jo = $conn->query($sql_jo);
    if ($result_jo && $result_jo->num_rows > 0) {
        $row = $result_jo->fetch_assoc();
        $jo_employees_count = (int) $row['count'];
    }

    $total_employees = $perm_employees_count + $cont_employees_count + $jo_employees_count;

    // ENHANCED DEPARTMENT DISTRIBUTION DATA
    $department_data = [];
    $department_colors = [];
    $department_breakdown = [];

    foreach ($tables as $type_name => $table_name) {
        // Check if table exists
        $table_check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table_name) . "'");
        if (!$table_check || $table_check->num_rows == 0) {
            continue;
        }

        // Check for department column - try common column names
        $possible_columns = ['department', 'department_name', 'office', 'section', 'division', 'unit'];
        $dept_column = null;

        foreach ($possible_columns as $col) {
            $col_check = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($table_name) . "` LIKE '$col'");
            if ($col_check && $col_check->num_rows > 0) {
                $dept_column = $col;
                break;
            }
        }

        // If no standard column found, try to find any column containing 'dept' or 'office'
        if (!$dept_column) {
            $columns = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($table_name) . "`");
            if ($columns) {
                while ($col = $columns->fetch_assoc()) {
                    $col_name = strtolower($col['Field']);
                    if (
                        strpos($col_name, 'dept') !== false ||
                        strpos($col_name, 'office') !== false ||
                        strpos($col_name, 'section') !== false ||
                        strpos($col_name, 'division') !== false ||
                        strpos($col_name, 'unit') !== false
                    ) {
                        $dept_column = $col['Field'];
                        break;
                    }
                }
            }
        }

        if ($dept_column) {
            $sql = "SELECT 
                    UPPER(TRIM(`" . $conn->real_escape_string($dept_column) . "`)) as dept, 
                    COUNT(*) as count
                   FROM `" . $conn->real_escape_string($table_name) . "` 
                   WHERE `" . $conn->real_escape_string($dept_column) . "` IS NOT NULL 
                   AND TRIM(`" . $conn->real_escape_string($dept_column) . "`) != ''
                   GROUP BY UPPER(TRIM(`" . $conn->real_escape_string($dept_column) . "`))";

            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $dept = !empty($row['dept']) ? $row['dept'] : 'Not Assigned';
                    $count = (int) $row['count'];

                    // Initialize department if not exists
                    if (!isset($department_data[$dept])) {
                        $department_data[$dept] = 0;
                        // Assign color based on department name hash
                        $color_index = crc32($dept) % count($color_palette);
                        $department_colors[$dept] = $color_palette[$color_index];
                        $department_breakdown[$dept] = [
                            'permanent' => 0,
                            'contractual' => 0,
                            'job_order' => 0
                        ];
                    }

                    // Add count to department total
                    $department_data[$dept] += $count;

                    // Update breakdown based on employee type
                    if ($table_name === 'permanent') {
                        $department_breakdown[$dept]['permanent'] += $count;
                    } elseif ($table_name === 'contractofservice') {
                        $department_breakdown[$dept]['contractual'] += $count;
                    } elseif ($table_name === 'job_order') {
                        $department_breakdown[$dept]['job_order'] += $count;
                    }
                }
            }
        }
    }

    // If no department data found, use total employees
    if (empty($department_data)) {
        $department_data = ['ALL DEPARTMENTS' => $total_employees];
        $department_colors = ['ALL DEPARTMENTS' => $color_palette[0]];
        $department_breakdown = [
            'ALL DEPARTMENTS' => [
                'permanent' => $perm_employees_count,
                'contractual' => $cont_employees_count,
                'job_order' => $jo_employees_count
            ]
        ];
    }

    // Sort by count (descending)
    arsort($department_data);

    // Prepare data for chart
    $dept_names = [];
    $dept_counts = [];
    $dept_colors_array = [];

    foreach ($department_data as $name => $count) {
        $dept_names[] = $name;
        $dept_counts[] = $count;
        $dept_colors_array[] = $department_colors[$name] ?? $color_palette[array_rand($color_palette)];
    }

    // Limit to top 10 departments for better visualization
    $max_departments = 10;
    if (count($dept_names) > $max_departments) {
        $dept_names = array_slice($dept_names, 0, $max_departments);
        $dept_counts = array_slice($dept_counts, 0, $max_departments);
        $dept_colors_array = array_slice($dept_colors_array, 0, $max_departments);

        // Add "Others" category
        $others_count = array_sum(array_slice($dept_counts, $max_departments));
        if ($others_count > 0) {
            $dept_names[] = 'Other Departments';
            $dept_counts[] = $others_count;
            $dept_colors_array[] = '#9ca3af'; // Gray color for Others
        }
    }

    // PAYROLL DATA
    $payroll_tables = ['permanent_payroll', 'contractual_payroll', 'joborder_payroll'];
    $payroll_processed = 0;
    $payroll_pending = 0;

    foreach ($payroll_tables as $payroll_table) {
        $check_table = $conn->query("SHOW TABLES LIKE '$payroll_table'");
        if ($check_table && $check_table->num_rows > 0) {
            // Count processed payroll (assuming status column exists)
            $sql_processed = "SELECT COUNT(*) as count FROM $payroll_table WHERE status = 'processed' AND MONTH(payroll_date) = MONTH(CURDATE())";
            $result_processed = $conn->query($sql_processed);
            if ($result_processed && $row = $result_processed->fetch_assoc()) {
                $payroll_processed += (int) $row['count'];
            }

            // Count pending payroll
            $sql_pending = "SELECT COUNT(*) as count FROM $payroll_table WHERE status = 'pending' OR status IS NULL";
            $result_pending = $conn->query($sql_pending);
            if ($result_pending && $row = $result_pending->fetch_assoc()) {
                $payroll_pending += (int) $row['count'];
            }
        }
    }

    $payroll_total = $payroll_processed + $payroll_pending;

    // ATTENDANCE DATA
    $check_attendance = $conn->query("SHOW TABLES LIKE 'attendance'");
    if ($check_attendance && $check_attendance->num_rows > 0) {
        $sql_attendance = "SELECT 
            DATE(attendance_date) as date,
            COUNT(DISTINCT employee_id) as count
            FROM attendance 
            WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(attendance_date)
            ORDER BY date";

        $result_attendance = $conn->query($sql_attendance);
        if ($result_attendance) {
            while ($row = $result_attendance->fetch_assoc()) {
                $attendance_dates[] = date('M d', strtotime($row['date']));
                $attendance_counts[] = (int) $row['count'];
            }
        }
    }

    // RECENT ACTIVITY DATA - Fetch from multiple tables
    $recent_activities = [];

    // 1. Recent employee additions (from all employee tables)
    $recent_employee_queries = [
        [
            'table' => 'permanent',
            'name_column' => 'full_name',
            'type' => 'Permanent Employee'
        ],
        [
            'table' => 'contractofservice',
            'name_column' => 'full_name',
            'type' => 'Contractual Employee'
        ],
        [
            'table' => 'job_order',
            'name_column' => 'full_name',
            'type' => 'Job Order'
        ]
    ];

    foreach ($recent_employee_queries as $query) {
        $table = $conn->real_escape_string($query['table']);
        $name_col = $conn->real_escape_string($query['name_column']);

        // Check if table has created_at column
        $check_col = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'created_at'");
        if ($check_col && $check_col->num_rows > 0) {
            $date_col = 'created_at';
        } else {
            // Try to find a date column
            $date_cols = $conn->query("SHOW COLUMNS FROM `$table` WHERE Type LIKE '%date%' OR Type LIKE '%time%'");
            if ($date_cols && $date_cols->num_rows > 0) {
                $date_col_row = $date_cols->fetch_assoc();
                $date_col = $date_col_row['Field'];
            } else {
                $date_col = null;
            }
        }

        if ($date_col) {
            $sql = "SELECT `$name_col` as name, `$date_col` as date, 
                   'employee_added' as activity_type, 
                   '{$query['type']}' as employee_type
                   FROM `$table`
                   WHERE `$date_col` >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                   ORDER BY `$date_col` DESC
                   LIMIT 5";

            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $recent_activities[] = [
                        'type' => 'employee_added',
                        'title' => 'New Employee Onboarded',
                        'description' => $row['name'] . ' joined as ' . $row['employee_type'],
                        'time' => $row['date'],
                        'icon' => 'user-check',
                        'icon_color' => 'success'
                    ];
                }
            }
        }
    }

    // 2. Recent attendance records
    $check_attendance_table = $conn->query("SHOW TABLES LIKE 'attendance'");
    if ($check_attendance_table && $check_attendance_table->num_rows > 0) {
        // Check if attendance has employee_id and attendance_date columns
        $attendance_cols = $conn->query("SHOW COLUMNS FROM attendance");
        $has_employee_id = false;
        $has_attendance_date = false;
        while ($col = $attendance_cols->fetch_assoc()) {
            if (strtolower($col['Field']) == 'employee_id')
                $has_employee_id = true;
            if (strtolower($col['Field']) == 'attendance_date')
                $has_attendance_date = true;
        }

        if ($has_employee_id && $has_attendance_date) {
            // Get recent attendance with employee names
            $sql_attendance = "SELECT 
                a.attendance_date as date,
                a.status,
                COALESCE(p.full_name, c.full_name, j.full_name) as employee_name
                FROM attendance a
                LEFT JOIN permanent p ON a.employee_id = p.id AND a.employee_type = 'permanent'
                LEFT JOIN contractofservice c ON a.employee_id = c.id AND a.employee_type = 'contractual'
                LEFT JOIN job_order j ON a.employee_id = j.id AND a.employee_type = 'job_order'
                WHERE a.attendance_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY a.attendance_date DESC
                LIMIT 5";

            $result_attendance = $conn->query($sql_attendance);
            if ($result_attendance) {
                while ($row = $result_attendance->fetch_assoc()) {
                    $status = isset($row['status']) ? $row['status'] : 'present';
                    $status_text = ucfirst($status);
                    $recent_activities[] = [
                        'type' => 'attendance',
                        'title' => 'Attendance Recorded',
                        'description' => $row['employee_name'] . ' marked as ' . $status_text,
                        'time' => $row['date'],
                        'icon' => 'calendar-check',
                        'icon_color' => 'primary'
                    ];
                }
            }
        }
    }

    // 3. Recent payroll processing
    $recent_payroll_queries = [
        ['table' => 'permanent_payroll', 'type' => 'Permanent'],
        ['table' => 'contractual_payroll', 'type' => 'Contractual'],
        ['table' => 'joborder_payroll', 'type' => 'Job Order']
    ];

    foreach ($recent_payroll_queries as $query) {
        $check_payroll = $conn->query("SHOW TABLES LIKE '{$query['table']}'");
        if ($check_payroll && $check_payroll->num_rows > 0) {
            // Check for payroll_date or created_at column
            $col_check = $conn->query("SHOW COLUMNS FROM `{$query['table']}` LIKE 'payroll_date'");
            if ($col_check && $col_check->num_rows > 0) {
                $date_col = 'payroll_date';
            } else {
                $col_check = $conn->query("SHOW COLUMNS FROM `{$query['table']}` LIKE 'created_at'");
                if ($col_check && $col_check->num_rows > 0) {
                    $date_col = 'created_at';
                } else {
                    continue;
                }
            }

            $sql_payroll = "SELECT COUNT(*) as count, $date_col as date 
                           FROM `{$query['table']}` 
                           WHERE $date_col >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                           AND status = 'processed'
                           GROUP BY DATE($date_col)
                           ORDER BY $date_col DESC
                           LIMIT 3";

            $result_payroll = $conn->query($sql_payroll);
            if ($result_payroll) {
                while ($row = $result_payroll->fetch_assoc()) {
                    if ($row['count'] > 0) {
                        $recent_activities[] = [
                            'type' => 'payroll',
                            'title' => 'Payroll Processed',
                            'description' => $row['count'] . ' ' . $query['type'] . ' employees payroll for ' . date('M d', strtotime($row['date'])),
                            'time' => $row['date'],
                            'icon' => 'money-bill-wave',
                            'icon_color' => 'warning'
                        ];
                    }
                }
            }
        }
    }

    $conn->close();
} catch (Exception $e) {
    // Use default values for demonstration
    error_log("Database error: " . $e->getMessage());

    $perm_employees_count = 45;
    $cont_employees_count = 32;
    $jo_employees_count = 23;
    $total_employees = 100;

    // Sample department data
    $department_data = [
        'HR DEPARTMENT' => 25,
        'FINANCE' => 18,
        'IT' => 15,
        'ADMINISTRATION' => 12,
        'OPERATIONS' => 10,
        'LOGISTICS' => 8,
        'MARKETING' => 7,
        'SALES' => 5
    ];

    $department_colors = [];
    foreach ($department_data as $dept => $count) {
        $color_index = crc32($dept) % count($color_palette);
        $department_colors[$dept] = $color_palette[$color_index];
    }

    $dept_names = array_keys($department_data);
    $dept_counts = array_values($department_data);
    $dept_colors_array = array_values($department_colors);

    $attendance_dates = [];
    $attendance_counts = [];

    // If no payroll data, use sample data
    if ($payroll_total == 0) {
        $payroll_processed = 85;
        $payroll_pending = 15;
        $payroll_total = 100;
    }

    // Sample recent activities if database fails
    $recent_activities = [
        [
            'type' => 'employee_added',
            'title' => 'New Employee Onboarded',
            'description' => 'John Doe joined as Software Engineer',
            'time' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'icon' => 'user-check',
            'icon_color' => 'success'
        ],
        [
            'type' => 'attendance',
            'title' => 'Attendance Recorded',
            'description' => 'Jane Smith marked as Present',
            'time' => date('Y-m-d H:i:s', strtotime('-4 hours')),
            'icon' => 'calendar-check',
            'icon_color' => 'primary'
        ],
        [
            'type' => 'payroll',
            'title' => 'Payroll Processed',
            'description' => 'Payroll for 45 permanent employees',
            'time' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'icon' => 'money-bill-wave',
            'icon_color' => 'warning'
        ]
    ];
}

// Sort recent activities by time (newest first)
usort($recent_activities, function ($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

// Take only the 3 most recent activities
$recent_activities = array_slice($recent_activities, 0, 3);

// Fill missing attendance dates with sample data
if (empty($attendance_dates)) {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('M d', strtotime("-$i days"));
        $attendance_dates[] = $date;
        // Generate realistic attendance (80-95% of total)
        $attendance_counts[] = $total_employees > 0 ? rand(floor($total_employees * 0.8), floor($total_employees * 0.95)) : 0;
    }
}

// Sort chronologically
if (!empty($attendance_dates) && !empty($attendance_counts)) {
    $dates_timestamps = array_map('strtotime', $attendance_dates);
    array_multisort($dates_timestamps, $attendance_counts);
    $attendance_dates = array_map(function ($ts) {
        return date('M d', $ts);
    }, $dates_timestamps);
}

// Prepare chart data with error handling
try {
    $dept_series = json_encode($dept_counts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_NUMERIC_CHECK);
    $dept_labels = json_encode($dept_names, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $dept_colors_json = json_encode($dept_colors_array, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $attendance_series = json_encode($attendance_counts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_NUMERIC_CHECK);
    $attendance_labels = json_encode($attendance_dates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
} catch (Exception $e) {
    // Fallback to empty JSON if encoding fails
    error_log("JSON encoding error: " . $e->getMessage());
    $dept_series = "[]";
    $dept_labels = "[]";
    $dept_colors_json = "[]";
    $attendance_series = "[]";
    $attendance_labels = "[]";
}

// Calculate attendance stats
$avg_attendance = !empty($attendance_counts) ? round(array_sum($attendance_counts) / count($attendance_counts)) : 0;
$today_attendance = !empty($attendance_counts) ? end($attendance_counts) : 0;
$attendance_rate = $total_employees > 0 ? round(($today_attendance / $total_employees) * 100) : 0;
$week_total = !empty($attendance_counts) ? array_sum($attendance_counts) : 0;

// Calculate payroll percentages
$payroll_processed_percent = $payroll_total > 0 ? round(($payroll_processed / $payroll_total) * 100) : 0;
$payroll_pending_percent = $payroll_total > 0 ? round(($payroll_pending / $payroll_total) * 100) : 0;

// Employee type counts
$employee_types = [
    'Permanent' => $perm_employees_count,
    'Contractual' => $cont_employees_count,
    'Job Order' => $jo_employees_count
];

// Set content type header to prevent output before headers
header('Content-Type: text/html; charset=UTF-8');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>HRMS Dashboard - Municipality of Paluan</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.1.1/flowbite.min.css" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== CSS RESET & BASE STYLES ===== */
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
            --gradient-primary-light: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
            --gradient-glass: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --shadow-blue: 0 10px 25px -3px rgba(30, 64, 175, 0.2);
            --shadow-card: 0 8px 30px rgba(0, 0, 0, 0.08);
            --shadow-glass: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
            --radius-sm: 12px;
            --radius-md: 16px;
            --radius-lg: 20px;
            --radius-xl: 24px;
            --radius-2xl: 28px;
            --radius-3xl: 32px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        /* Payroll Dropdown Styles */
        .sidebar-dropdown {
            position: relative;
        }

        .dropdown-menu {
            display: none;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 0 0 12px 12px;
            margin: 0;
            padding: 0.5rem 0;
            border-left: 3px solid rgba(255, 255, 255, 0.2);
            margin-left: 1.5rem;
            animation: fadeIn 0.3s ease;
        }

        .dropdown-menu.active {
            display: block;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 1.25rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .dropdown-item:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            padding-left: 1.5rem;
        }

        .dropdown-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: transparent;
            transition: background 0.3s ease;
        }

        .dropdown-item:hover::before {
            background: white;
        }

        .dropdown-item i {
            font-size: 0.6rem;
        }

        /* Chevron animation */
        .chevron {
            transition: transform 0.3s ease;
        }

        .chevron.rotate {
            transform: rotate(180deg);
        }

        /* Animation for dropdown */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        html,
        body {
            height: 100%;
            width: 100%;
            overflow-x: hidden;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            color: var(--dark);
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 30%, #f8fafc 100%);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        /* ===== NAVBAR STYLES ===== */
        .navbar {
            background: var(--gradient-primary);
            box-shadow: var(--shadow-md);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 70px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .navbar-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            padding: 0 1rem;
            width: 100%;
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

        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            transition: transform 0.3s ease;
            z-index: 1002;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            transition: transform 0.3s ease;
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

        /* Date Time */
        .datetime-container {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .datetime-box {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-md);
            padding: 0.4rem 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
            min-width: 120px;
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
            min-width: auto;
        }

        .user-button:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl), var(--shadow-blue);
            min-width: 220px;
            display: none;
            z-index: 1001;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            animation: slideDown 0.3s ease;
        }

        .user-dropdown.active {
            display: block;
        }

        .dropdown-header {
            padding: 1.25rem;
            background: var(--gradient-primary);
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
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
            font-size: 1.1rem;
        }

        .dropdown-item.logout:hover {
            background: #fef2f2;
            color: var(--danger);
        }

        .dropdown-item.logout:hover i {
            color: var(--danger);
        }


        .sidebar-container::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-container::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-container::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        .sidebar {
            height: 100%;
            padding: 1.5rem 0;
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

        /* Dropdown Menu in Sidebar */
        .dropdown-menu {
            display: none;
            padding-left: 1rem;
            margin-left: 2.5rem;
            border-left: 2px solid rgba(255, 255, 255, 0.1);
            animation: fadeIn 0.3s ease;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-menu .dropdown-item {
            padding: 0.7rem 1rem;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            border-bottom: none;
        }

        .dropdown-menu .dropdown-item:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .chevron {
            transition: transform 0.3s ease;
        }

        .chevron.rotate {
            transform: rotate(180deg);
        }

        .sidebar-footer {
            margin-top: 330px;
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: white;
        }

        /* ===== IMPROVED MAIN CONTENT ===== */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 2rem;
            min-height: calc(100vh - 70px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            width: calc(100% - 260px);
            overflow-x: hidden;
            flex: 1;
            background: linear-gradient(135deg, #f8fafc 0%, #f0f9ff 30%, #e0f2fe 100%);
            position: relative;
        }

        .main-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 400px;
            background: linear-gradient(135deg, rgba(30, 64, 175, 0.03) 0%, rgba(59, 130, 246, 0.02) 100%);
            z-index: 0;
            pointer-events: none;
        }

        .container {
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
            padding: 0;
            position: relative;
            z-index: 1;
        }

        /* ===== DASHBOARD HEADER - ENHANCED ===== */
        .dashboard-header {
            margin-bottom: 2.5rem;
            animation: slideUp 0.6s ease-out;
            width: 100%;
            padding: 2.5rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.98) 100%);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-glass);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg,
                    var(--primary) 0%,
                    var(--secondary) 25%,
                    var(--success) 50%,
                    var(--warning) 75%,
                    var(--info) 100%);
            border-radius: var(--radius-2xl) var(--radius-2xl) 0 0;
        }

        .dashboard-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%233b82f6' fill-opacity='0.03' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.3;
            pointer-events: none;
        }

        .dashboard-title {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 900;
            color: transparent;
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg,
                    var(--primary) 0%,
                    var(--secondary) 25%,
                    var(--info) 50%,
                    var(--primary-dark) 75%,
                    var(--primary) 100%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            background-clip: text;
            letter-spacing: -1px;
            line-height: 1.1;
            position: relative;
            display: inline-block;
            animation: gradientShift 3s ease infinite;
        }

        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .dashboard-subtitle {
            color: var(--gray-600);
            font-size: clamp(1rem, 2vw, 1.25rem);
            font-weight: 500;
            line-height: 1.5;
            margin-top: 0.5rem;
            max-width: 800px;
        }

        .welcome-card {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(30, 64, 175, 0.05) 0%, rgba(59, 130, 246, 0.03) 100%);
            border-radius: var(--radius-xl);
            border: 1px solid rgba(59, 130, 246, 0.1);
            backdrop-filter: blur(10px);
            transition: var(--transition);
        }

        .welcome-card:hover {
            transform: translateY(-2px);
            border-color: rgba(59, 130, 246, 0.2);
            box-shadow: 0 10px 25px rgba(30, 64, 175, 0.1);
        }

        .welcome-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 8px 20px rgba(30, 64, 175, 0.2);
            flex-shrink: 0;
        }

        .welcome-content {
            flex: 1;
        }

        .welcome-content h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .welcome-content p {
            font-size: 0.95rem;
            color: var(--gray-600);
            line-height: 1.5;
        }

        /* ===== STATS GRID - ENHANCED GLASSMORPHISM ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.75rem;
            margin-bottom: 3rem;
            width: 100%;
        }

        .stat-card {
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.9) 0%,
                    rgba(255, 255, 255, 0.8) 100%);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            box-shadow: var(--shadow-glass);
            border: 1px solid rgba(255, 255, 255, 0.4);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            min-height: 220px;
            display: flex;
            flex-direction: column;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100%;
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.2) 0%,
                    rgba(255, 255, 255, 0) 100%);
            z-index: 1;
            pointer-events: none;
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(30, 64, 175, 0.25);
            border-color: rgba(255, 255, 255, 0.6);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle,
                    rgba(255, 255, 255, 0.1) 0%,
                    rgba(255, 255, 255, 0) 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        .stat-card:hover::after {
            opacity: 1;
        }

        .stat-card.primary {
            border-top: 4px solid var(--primary);
        }

        .stat-card.success {
            border-top: 4px solid var(--success);
        }

        .stat-card.warning {
            border-top: 4px solid var(--warning);
        }

        .stat-card.danger {
            border-top: 4px solid var(--danger);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 2;
        }

        .stat-icon-wrapper {
            position: relative;
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            position: relative;
            overflow: hidden;
        }

        .stat-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: inherit;
            opacity: 0.2;
            z-index: -1;
        }

        .stat-icon.primary {
            background: linear-gradient(135deg,
                    rgba(30, 64, 175, 0.1) 0%,
                    rgba(59, 130, 246, 0.2) 100%);
            color: var(--primary);
            border: 2px solid rgba(30, 64, 175, 0.2);
        }

        .stat-icon.success {
            background: linear-gradient(135deg,
                    rgba(16, 185, 129, 0.1) 0%,
                    rgba(34, 211, 153, 0.2) 100%);
            color: var(--success);
            border: 2px solid rgba(16, 185, 129, 0.2);
        }

        .stat-icon.warning {
            background: linear-gradient(135deg,
                    rgba(245, 158, 11, 0.1) 0%,
                    rgba(251, 191, 36, 0.2) 100%);
            color: var(--warning);
            border: 2px solid rgba(245, 158, 11, 0.2);
        }

        .stat-icon.danger {
            background: linear-gradient(135deg,
                    rgba(239, 68, 68, 0.1) 0%,
                    rgba(248, 113, 113, 0.2) 100%);
            color: var(--danger);
            border: 2px solid rgba(239, 68, 68, 0.2);
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.8);
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
            justify-content: center;
            position: relative;
            z-index: 2;
        }

        .stat-content h3 {
            font-size: clamp(2.25rem, 3vw, 3rem);
            font-weight: 900;
            margin-bottom: 0.5rem;
            color: var(--dark);
            line-height: 1.1;
            background: linear-gradient(135deg, var(--dark) 0%, var(--gray-700) 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .stat-card.primary .stat-content h3 {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .stat-card.success .stat-content h3 {
            background: linear-gradient(135deg, var(--success) 0%, #34d399 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .stat-card.warning .stat-content h3 {
            background: linear-gradient(135deg, var(--warning) 0%, #fbbf24 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .stat-card.danger .stat-content h3 {
            background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stat-label i {
            font-size: 0.9rem;
            opacity: 0.7;
        }

        .stat-detail {
            color: var(--gray-500);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }

        .stat-progress {
            margin-top: auto;
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 4px;
            overflow: hidden;
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .stat-progress-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 1.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg,
                    transparent 0%,
                    rgba(255, 255, 255, 0.4) 50%,
                    transparent 100%);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% {
                transform: translateX(-100%);
            }

            100% {
                transform: translateX(100%);
            }
        }

        .stat-card.primary .stat-progress-bar {
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
        }

        .stat-card.success .stat-progress-bar {
            background: linear-gradient(90deg, var(--success) 0%, #34d399 100%);
        }

        .stat-card.warning .stat-progress-bar {
            background: linear-gradient(90deg, var(--warning) 0%, #fbbf24 100%);
        }

        .stat-card.danger .stat-progress-bar {
            background: linear-gradient(90deg, var(--danger) 0%, #f87171 100%);
        }

        /* ===== ENHANCED CHARTS GRID ===== */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
            width: 100%;
        }

        @media (max-width: 1200px) {
            .charts-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        .chart-card {
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.95) 0%,
                    rgba(255, 255, 255, 0.9) 100%);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-2xl);
            padding: 2.5rem;
            box-shadow: var(--shadow-glass);
            border: 1px solid rgba(255, 255, 255, 0.4);
            animation: fadeIn 0.8s ease-out;
            width: 100%;
            overflow: hidden;
            position: relative;
            min-height: 550px;
            display: flex;
            flex-direction: column;
            transition: var(--transition);
        }

        .chart-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 25px 50px -12px rgba(30, 64, 175, 0.15);
            border-color: rgba(255, 255, 255, 0.6);
        }

        .chart-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--gradient-primary);
            z-index: 1;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            flex-shrink: 0;
            position: relative;
            z-index: 2;
        }

        .chart-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 1rem;
            line-height: 1.3;
        }

        .chart-title i {
            width: 50px;
            height: 50px;
            background: var(--gradient-primary);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 8px 20px rgba(30, 64, 175, 0.2);
        }

        .chart-subtitle {
            color: var(--gray-500);
            font-size: 1rem;
            margin-top: 0.75rem;
            line-height: 1.5;
            max-width: 90%;
        }

        .chart-actions {
            display: flex;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            padding: 0.5rem;
            border-radius: var(--radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .chart-action-btn {
            background: transparent;
            border: none;
            border-radius: var(--radius-md);
            padding: 0.75rem 1.5rem;
            font-size: 0.9rem;
            color: var(--gray-600);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            white-space: nowrap;
        }

        .chart-action-btn:hover {
            background: rgba(255, 255, 255, 0.9);
            color: var(--primary);
        }

        .chart-action-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Enhanced Chart Controls */
        .chart-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin: 1.5rem 0;
            padding: 1.5rem;
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.8) 0%,
                    rgba(255, 255, 255, 0.6) 100%);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-xl);
            border: 1px solid rgba(255, 255, 255, 0.4);
            align-items: center;
        }

        .control-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .control-group label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .control-group label i {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .form-control-sm {
            padding: 0.5rem 1rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            background: white;
            color: var(--gray-800);
            min-width: 120px;
            transition: var(--transition);
        }

        .form-control-sm:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Chart Container */
        .chart-container {
            flex: 1;
            min-height: 380px;
            position: relative;
            margin-top: auto;
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        #attendance-chart,
        #department-chart {
            width: 100% !important;
            height: 100% !important;
            min-height: 350px;
        }

        /* Department Details Panel */
        .details-panel {
            position: absolute;
            top: 2rem;
            right: 2rem;
            width: 320px;
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.95) 0%,
                    rgba(255, 255, 255, 0.9) 100%);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.4);
            z-index: 100;
            opacity: 0;
            transform: translateX(20px);
            transition: var(--transition);
            pointer-events: none;
        }

        .details-panel.active {
            opacity: 1;
            transform: translateX(0);
            pointer-events: all;
        }

        .details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.4);
            background: linear-gradient(135deg,
                    rgba(30, 64, 175, 0.05) 0%,
                    rgba(59, 130, 246, 0.03) 100%);
        }

        .details-header h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .details-header h4 i {
            color: var(--primary);
        }

        .details-close {
            background: none;
            border: none;
            color: var(--gray-500);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .details-close:hover {
            background: var(--gray-100);
            color: var(--danger);
        }

        .details-content {
            padding: 1.5rem;
        }

        .dept-summary {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem;
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.6) 0%,
                    rgba(255, 255, 255, 0.4) 100%);
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .dept-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .dept-info {
            flex: 1;
        }

        .dept-info h5 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }

        .dept-info p {
            font-size: 0.9rem;
            color: var(--gray-600);
            margin-bottom: 0.25rem;
        }

        .dept-breakdown {
            max-height: 200px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .dept-breakdown::-webkit-scrollbar {
            width: 6px;
        }

        .dept-breakdown::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        .dept-breakdown::-webkit-scrollbar-thumb {
            background: rgba(59, 130, 246, 0.3);
            border-radius: 3px;
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.4) 0%,
                    rgba(255, 255, 255, 0.2) 100%);
            border-radius: var(--radius-md);
            border: 1px solid rgba(255, 255, 255, 0.6);
            transition: var(--transition);
        }

        .breakdown-item:hover {
            transform: translateX(5px);
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.6) 0%,
                    rgba(255, 255, 255, 0.4) 100%);
        }

        .breakdown-item .type {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .breakdown-item .type i {
            width: 20px;
            text-align: center;
            color: var(--primary);
        }

        .breakdown-item .count {
            font-size: 1rem;
            font-weight: 800;
            color: var(--dark);
            background: rgba(255, 255, 255, 0.6);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
        }

        .no-data {
            text-align: center;
            color: var(--gray-500);
            font-style: italic;
            padding: 2rem;
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.4) 0%,
                    rgba(255, 255, 255, 0.2) 100%);
            border-radius: var(--radius-md);
            border: 2px dashed rgba(255, 255, 255, 0.6);
        }

        /* Custom Tooltip Styles */
        .custom-tooltip {
            padding: 1rem;
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.95) 0%,
                    rgba(255, 255, 255, 0.9) 100%);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: var(--shadow-lg);
            min-width: 200px;
        }

        .tooltip-header {
            font-size: 1rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary);
        }

        .tooltip-body {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .tooltip-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
        }

        .tooltip-row span {
            color: var(--gray-600);
            font-weight: 500;
        }

        .tooltip-row strong {
            color: var(--dark);
            font-weight: 700;
            font-size: 1.1em;
        }

        /* ===== ENHANCED TYPE STATS ===== */
        .type-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.6);
            position: relative;
            z-index: 2;
        }

        @media (max-width: 768px) {
            .type-stats {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        .type-stat {
            text-align: center;
            padding: 1.75rem;
            border-radius: var(--radius-xl);
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.8) 0%,
                    rgba(255, 255, 255, 0.6) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            transition: var(--transition);
            width: 100%;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .type-stat:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            border-color: rgba(255, 255, 255, 1);
        }

        .type-stat::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
        }

        .type-stat.primary::before {
            background: var(--gradient-primary);
        }

        .type-stat.warning::before {
            background: var(--gradient-warning);
        }

        .type-stat.info::before {
            background: var(--gradient-secondary);
        }

        .type-stat h4 {
            font-size: 2.25rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
            line-height: 1;
            background: linear-gradient(135deg, var(--dark) 0%, var(--gray-700) 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .type-stat.primary h4 {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .type-stat.warning h4 {
            background: linear-gradient(135deg, var(--warning) 0%, #fbbf24 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .type-stat.info h4 {
            background: linear-gradient(135deg, var(--secondary) 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .type-stat p {
            font-size: 1rem;
            color: var(--gray-600);
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .type-stat .percentage {
            font-size: 0.9rem;
            color: var(--gray-500);
            font-weight: 500;
            padding: 0.5rem 1.25rem;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 50px;
            display: inline-block;
            border: 1px solid rgba(255, 255, 255, 0.9);
        }

        /* ===== ENHANCED PAYROLL STATS ===== */
        .payroll-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin: 2.5rem 0;
            width: 100%;
        }

        @media (max-width: 576px) {
            .payroll-stats {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }

        .payroll-stat {
            text-align: center;
            padding: 2.5rem;
            border-radius: var(--radius-2xl);
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.9) 0%,
                    rgba(255, 255, 255, 0.8) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: var(--shadow-glass);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            width: 100%;
        }

        .payroll-stat:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            border-color: rgba(255, 255, 255, 0.6);
        }

        .payroll-stat::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
        }

        .payroll-stat.processed::before {
            background: var(--gradient-success);
        }

        .payroll-stat.pending::before {
            background: var(--gradient-warning);
        }

        .payroll-stat-icon {
            width: 80px;
            height: 80px;
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .payroll-stat.processed .payroll-stat-icon {
            background: var(--gradient-success);
            color: white;
        }

        .payroll-stat.pending .payroll-stat-icon {
            background: var(--gradient-warning);
            color: white;
        }

        .payroll-stat h4 {
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 0.75rem;
            line-height: 1;
            background: linear-gradient(135deg, var(--dark) 0%, var(--gray-700) 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .payroll-stat.processed h4 {
            background: linear-gradient(135deg, var(--success) 0%, #34d399 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .payroll-stat.pending h4 {
            background: linear-gradient(135deg, var(--warning) 0%, #fbbf24 100%);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .payroll-stat p {
            font-size: 1.25rem;
            color: var(--gray-700);
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .payroll-stat small {
            display: block;
            font-size: 1rem;
            color: var(--gray-500);
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.6);
            border-radius: var(--radius-lg);
            margin-top: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        /* ===== ENHANCED SUMMARY CARDS ===== */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.75rem;
            margin-top: 2.5rem;
        }

        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: 1fr;
                gap: 1.25rem;
            }
        }

        .summary-card {
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.9) 0%,
                    rgba(255, 255, 255, 0.8) 100%);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-glass);
            border: 1px solid rgba(255, 255, 255, 0.4);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .summary-card:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
            border-color: rgba(255, 255, 255, 0.6);
        }

        .summary-icon {
            width: 70px;
            height: 70px;
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
        }

        .summary-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: inherit;
            opacity: 0.8;
            z-index: -1;
        }

        .summary-content {
            flex: 1;
        }

        .summary-content h4 {
            font-size: 2rem;
            font-weight: 900;
            color: var(--dark);
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .summary-content p {
            font-size: 1rem;
            color: var(--gray-600);
            font-weight: 500;
            line-height: 1.4;
        }

        /* ===== ENHANCED RECENT ACTIVITY ===== */
        .activity-card {
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.95) 0%,
                    rgba(255, 255, 255, 0.9) 100%);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-2xl);
            padding: 2.5rem;
            box-shadow: var(--shadow-glass);
            border: 1px solid rgba(255, 255, 255, 0.4);
            height: 100%;
            display: flex;
            flex-direction: column;
            min-height: 550px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .activity-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 25px 50px -12px rgba(30, 64, 175, 0.15);
            border-color: rgba(255, 255, 255, 0.6);
        }

        .activity-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--gradient-secondary);
        }

        .activity-list {
            list-style: none;
            margin-top: 1.5rem;
            flex: 1;
            overflow-y: auto;
            max-height: 400px;
            padding-right: 10px;
            position: relative;
            z-index: 2;
        }

        .activity-list::-webkit-scrollbar {
            width: 8px;
        }

        .activity-list::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
        }

        .activity-list::-webkit-scrollbar-thumb {
            background: rgba(59, 130, 246, 0.3);
            border-radius: 4px;
        }

        .activity-list::-webkit-scrollbar-thumb:hover {
            background: rgba(59, 130, 246, 0.5);
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            padding: 1.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.6);
            transition: var(--transition);
            border-radius: var(--radius-lg);
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.6) 0%,
                    rgba(255, 255, 255, 0.4) 100%);
            backdrop-filter: blur(10px);
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }

        .activity-item:hover {
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.8) 0%,
                    rgba(255, 255, 255, 0.6) 100%);
            transform: translateX(8px) scale(1.02);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            border-color: rgba(255, 255, 255, 0.8);
        }

        .activity-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .activity-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
            position: relative;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.6);
        }

        .activity-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.15;
        }

        .activity-icon.success {
            background: linear-gradient(135deg,
                    rgba(16, 185, 129, 0.2) 0%,
                    rgba(34, 211, 153, 0.3) 100%);
            color: var(--success);
        }

        .activity-icon.warning {
            background: linear-gradient(135deg,
                    rgba(245, 158, 11, 0.2) 0%,
                    rgba(251, 191, 36, 0.3) 100%);
            color: var(--warning);
        }

        .activity-icon.primary {
            background: linear-gradient(135deg,
                    rgba(30, 64, 175, 0.2) 0%,
                    rgba(59, 130, 246, 0.3) 100%);
            color: var(--primary);
        }

        .activity-icon.info {
            background: linear-gradient(135deg,
                    rgba(99, 102, 241, 0.2) 0%,
                    rgba(139, 92, 246, 0.3) 100%);
            color: var(--secondary);
        }

        .activity-content {
            flex: 1;
            min-width: 0;
        }

        .activity-content h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .activity-content p {
            font-size: 0.95rem;
            color: var(--gray-600);
            margin-bottom: 0.75rem;
            line-height: 1.5;
        }

        .activity-time {
            font-size: 0.85rem;
            color: var(--gray-500);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .activity-time i {
            font-size: 0.8rem;
        }

        /* ===== ENHANCED QUICK ACTIONS ===== */
        .quick-actions-section {
            margin-top: 3rem;
            padding: 2.5rem;
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.95) 0%,
                    rgba(255, 255, 255, 0.9) 100%);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-glass);
            border: 1px solid rgba(255, 255, 255, 0.4);
            position: relative;
            overflow: hidden;
        }

        .quick-actions-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--gradient-primary-light);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2.5rem;
            position: relative;
            z-index: 2;
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .section-title i {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary-light);
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.75rem;
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
        }

        .section-subtitle {
            color: var(--gray-500);
            font-size: 1rem;
            margin-top: 0.75rem;
            line-height: 1.5;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.75rem;
            width: 100%;
        }

        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 1.5rem;
            padding: 2.5rem 2rem;
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.8) 0%,
                    rgba(255, 255, 255, 0.6) 100%);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-2xl);
            text-decoration: none;
            color: var(--dark);
            box-shadow: var(--shadow-glass);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.4);
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .quick-action::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--gradient-primary);
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: 1;
        }

        .quick-action:hover {
            transform: translateY(-10px) scale(1.05);
            box-shadow: 0 30px 60px -12px rgba(30, 64, 175, 0.3);
            border-color: rgba(255, 255, 255, 0.8);
        }

        .quick-action:hover::before {
            opacity: 1;
        }

        .quick-action:hover .action-icon,
        .quick-action:hover .action-text h4,
        .quick-action:hover .action-text p {
            color: white;
            position: relative;
            z-index: 2;
        }

        .quick-action:hover .action-icon {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.15) rotate(10deg);
        }

        .action-icon {
            width: 80px;
            height: 80px;
            border-radius: var(--radius-2xl);
            background: var(--gradient-primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 12px 25px rgba(59, 130, 246, 0.3);
            position: relative;
            z-index: 2;
        }

        .quick-action:nth-child(2) .action-icon {
            background: var(--gradient-success);
            box-shadow: 0 12px 25px rgba(16, 185, 129, 0.3);
        }

        .quick-action:nth-child(3) .action-icon {
            background: var(--gradient-warning);
            box-shadow: 0 12px 25px rgba(245, 158, 11, 0.3);
        }

        .quick-action:nth-child(4) .action-icon {
            background: var(--gradient-secondary);
            box-shadow: 0 12px 25px rgba(99, 102, 241, 0.3);
        }

        .action-text {
            flex: 1;
            position: relative;
            z-index: 2;
        }

        .action-text h4 {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            transition: var(--transition);
            line-height: 1.2;
        }

        .action-text p {
            font-size: 1rem;
            color: var(--gray-600);
            font-weight: 500;
            line-height: 1.5;
            transition: var(--transition);
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
            padding: 20px;
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-container {
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.95) 0%,
                    rgba(255, 255, 255, 0.9) 100%);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-2xl);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: slideUp 0.4s ease;
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        .modal-header {
            padding: 1.75rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.4);
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: var(--radius-2xl) var(--radius-2xl) 0 0;
            position: relative;
        }

        .modal-header h2 {
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .modal-header h2 i {
            font-size: 1.25rem;
        }

        .modal-close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
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
            padding: 2rem;
        }

        .profile-picture-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-avatar-container {
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .profile-avatar {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }

        .profile-avatar:hover {
            transform: scale(1.05);
        }

        .change-avatar-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
            transition: var(--transition);
        }

        .change-avatar-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

        .avatar-input {
            display: none;
        }

        .profile-info {
            display: grid;
            gap: 1.5rem;
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
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            color: var(--gray-800);
            transition: var(--transition);
            background: var(--gray-50);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-control[readonly] {
            background: var(--gray-100);
            color: var(--gray-600);
            cursor: not-allowed;
        }

        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-upload-wrapper input[type=file] {
            font-size: 100px;
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--gray-100);
            border: 2px dashed var(--gray-400);
            border-radius: var(--radius-md);
            color: var(--gray-700);
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .file-upload-label:hover {
            background: var(--gray-200);
            border-color: var(--primary);
        }

        .file-info {
            font-size: 0.85rem;
            color: var(--gray-600);
            margin-top: 0.5rem;
            text-align: center;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.4);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.8) 0%,
                    rgba(255, 255, 255, 0.6) 100%);
            backdrop-filter: blur(10px);
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
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 64, 175, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.8) 0%,
                    rgba(255, 255, 255, 0.6) 100%);
            color: var(--gray-700);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg,
                    rgba(255, 255, 255, 0.9) 0%,
                    rgba(255, 255, 255, 0.7) 100%);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
            color: white;
        }

        .btn-danger:hover {
            background: var(--danger-dark);
            transform: translateY(-2px);
        }

        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .user-info-item {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            padding: 1rem;
            border-radius: var(--radius-md);
            border-left: 4px solid var(--primary);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .user-info-item label {
            display: block;
            font-size: 0.8rem;
            color: var(--gray-600);
            margin-bottom: 0.25rem;
            font-weight: 500;
        }

        .user-info-item span {
            display: block;
            font-size: 0.95rem;
            color: var(--gray-800);
            font-weight: 600;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease;
            backdrop-filter: blur(10px);
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

        .preview-container {
            margin-top: 1rem;
            text-align: center;
        }

        .preview-image {
            max-width: 150px;
            max-height: 150px;
            border-radius: var(--radius-md);
            margin: 0 auto;
            display: block;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* ===== SCROLL TO TOP ===== */
        .scroll-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.5rem;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(30, 64, 175, 0.3);
            transition: var(--transition);
            z-index: 998;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .scroll-top:hover {
            transform: translateY(-8px) scale(1.1);
            box-shadow: 0 20px 40px rgba(30, 64, 175, 0.4);
        }

        .scroll-top.show {
            display: flex;
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
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

        /* Apply animations to cards */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        .slide-up {
            animation: slideUp 0.8s ease-out;
        }

        /* Stagger animations */
        .stat-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .stat-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .stat-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .stat-card:nth-child(4) {
            animation-delay: 0.4s;
        }

        .chart-card:nth-child(1) {
            animation-delay: 0.2s;
        }

        .chart-card:nth-child(2) {
            animation-delay: 0.3s;
        }

        .activity-card {
            animation-delay: 0.4s;
        }

        .quick-actions-section {
            animation-delay: 0.5s;
        }

        /* Loading animation */
        .loading {
            position: relative;
            overflow: hidden;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg,
                    transparent,
                    rgba(255, 255, 255, 0.4),
                    transparent);
            animation: shimmer 1.5s infinite;
        }

        /* Overlay for mobile menu */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            display: none;
            backdrop-filter: blur(4px);
        }

        .overlay.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        /* Responsive adjustments for details panel */
        @media (max-width: 1200px) {
            .details-panel {
                position: relative;
                top: auto;
                right: auto;
                width: 100%;
                margin-top: 2rem;
                transform: translateY(20px);
            }

            .details-panel.active {
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .chart-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .control-group {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-control-sm {
                width: 100%;
            }
        }

        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 1200px) {
            .main-content {
                padding: 1.5rem;
                margin-left: 0;
                width: 100%;
            }

            .sidebar-container {
                transform: translateX(-100%);
                width: 280px;
            }

            .sidebar-container.active {
                transform: translateX(0);
            }

            .container {
                padding: 0 0.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .dashboard-header {
                padding: 2rem;
            }

            .chart-card {
                min-height: 500px;
            }
        }

        @media (max-width: 992px) {
            .main-content {
                padding: 1.25rem;
            }

            .dashboard-header {
                padding: 1.75rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
                gap: 1.25rem;
            }

            .chart-card {
                padding: 2rem;
                min-height: 480px;
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
                margin-top: 60px;
            }

            .navbar {
                height: 60px;
            }

            .sidebar-container {
                top: 60px;
                height: calc(100vh - 60px);
            }

            .dashboard-header {
                padding: 1.5rem;
            }

            .dashboard-title {
                font-size: 1.75rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .stat-card {
                padding: 1.75rem;
            }

            .stat-icon {
                width: 60px;
                height: 60px;
                font-size: 1.75rem;
            }

            .charts-grid {
                gap: 1.5rem;
            }

            .chart-card {
                min-height: 450px;
                padding: 1.75rem;
            }

            .quick-actions {
                grid-template-columns: 1fr;
                gap: 1.25rem;
            }

            .quick-action {
                padding: 2rem 1.5rem;
            }

            .activity-card {
                min-height: 450px;
                padding: 1.75rem;
            }

            .type-stats {
                gap: 1rem;
            }

            .type-stat {
                padding: 1.5rem;
            }

            .payroll-stats {
                gap: 1.25rem;
            }

            .payroll-stat {
                padding: 2rem;
            }

            .scroll-top {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
                bottom: 1.5rem;
                right: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 0.875rem;
            }

            .dashboard-header {
                padding: 1.25rem;
            }

            .dashboard-title {
                font-size: 1.5rem;
            }

            .welcome-card {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
                padding: 1.25rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .stat-content h3 {
                font-size: 1.75rem;
            }

            .chart-card {
                padding: 1.5rem;
                min-height: 400px;
            }

            .chart-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
                margin-bottom: 1.5rem;
            }

            .chart-title {
                font-size: 1.5rem;
            }

            .chart-actions {
                width: 100%;
                justify-content: space-between;
            }

            .chart-action-btn {
                flex: 1;
                text-align: center;
                padding: 0.75rem;
                font-size: 0.85rem;
            }

            .quick-action {
                padding: 1.75rem 1.25rem;
            }

            .action-icon {
                width: 70px;
                height: 70px;
                font-size: 1.75rem;
            }

            .action-text h4 {
                font-size: 1.25rem;
            }

            .user-info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 400px) {
            .main-content {
                padding: 0.75rem 0.5rem;
            }

            .stat-card {
                padding: 1.25rem;
            }

            .stat-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
            }

            .stat-content h3 {
                font-size: 1.5rem;
            }

            .chart-card {
                padding: 1.25rem;
                min-height: 380px;
            }

            .chart-container {
                padding: 1rem;
            }

            .quick-action {
                padding: 1.5rem 1rem;
            }

            .activity-item {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 1rem;
                padding: 1.5rem;
            }
        }

        /* Landscape Mode Optimization */
        @media (max-height: 600px) and (orientation: landscape) {
            .navbar {
                height: 50px;
            }

            .main-content {
                margin-top: 50px;
                padding: 0.5rem;
            }

            .sidebar-container {
                top: 50px;
                height: calc(100vh - 50px);
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .stat-card {
                padding: 1rem;
                min-height: 180px;
            }

            .charts-grid {
                gap: 0.75rem;
            }

            .chart-card {
                padding: 1.25rem;
                min-height: 300px;
            }

            #attendance-chart,
            #department-chart {
                min-height: 200px;
            }
        }

        /* High Resolution Screens */
        @media (min-width: 1920px) {
            .container {
                max-width: 1800px;
            }

            .main-content {
                padding: 2rem 3rem;
            }

            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 2rem;
            }

            .chart-card {
                padding: 2rem;
                min-height: 550px;
            }

            #attendance-chart,
            #department-chart {
                min-height: 400px;
            }

            .quick-actions {
                grid-template-columns: repeat(4, 1fr);
                gap: 1.5rem;
            }
        }

        /* Touch Device Optimizations */
        @media (hover: none) and (pointer: coarse) {

            .stat-card:hover,
            .quick-action:hover,
            .sidebar-item:hover {
                transform: none;
            }

            .chart-action-btn,
            .user-button,
            .mobile-toggle {
                min-height: 44px;
                min-width: 44px;
            }

            .dropdown-item {
                padding: 1rem 1.25rem;
            }
        }

        /* Reduced Motion Preferences */
        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
    <style>
        /* Your existing CSS styles remain the same */
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
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --shadow-blue: 0 10px 25px -3px rgba(30, 64, 175, 0.2);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            color: var(--dark);
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f8fafc 100%);
            overflow-x: hidden;
        }

        /* Navigation */
        .navbar {
            background: var(--gradient-primary);
            box-shadow: var(--shadow-md);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 70px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .navbar-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            padding: 0 1rem;
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
            gap: 1rem;
        }

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            transition: transform 0.3s ease;
        }

        .mobile-toggle:hover {
            transform: scale(1.1);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            transition: transform 0.3s ease;
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
            border-radius: 12px;
            padding: 0.5rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
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

        /* Sidebar */
        .sidebar-container {
            position: fixed;
            left: 0;
            top: 70px;
            width: 260px;
            height: calc(100vh - 70px);
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 100%);
            z-index: 999;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }

        .sidebar {
            height: 100%;
            padding: 1.5rem 0;
        }

        .sidebar-content {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            padding: 0 1rem;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.9rem 1.25rem;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 12px;
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

        .sidebar-footer {
            margin-top: auto;
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: white;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 1.5rem;
            min-height: calc(100vh - 70px);
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Modal Styles */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1100;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: none;
        }

        .modal-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1101;
            display: none;
            padding: 1rem;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            width: 100%;
        }

        @media (min-width: 768px) {
            .modal-content {
                max-width: 800px;
            }
        }

        @media (min-width: 1024px) {
            .modal-content {
                max-width: 1000px;
            }
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.25rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .action-btn i {
            font-size: 14px;
        }

        .action-btn.view {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .action-btn.view:hover {
            background-color: #2563eb;
            border-color: #2563eb;
        }

        .action-btn.edit {
            background-color: #10b981;
            color: white;
            border-color: #10b981;
        }

        .action-btn.edit:hover {
            background-color: #059669;
            border-color: #059669;
        }

        .action-btn.inactive {
            background-color: #f59e0b;
            color: white;
            border-color: #f59e0b;
        }

        .action-btn.inactive:hover {
            background-color: #d97706;
            border-color: #d97706;
        }

        .action-btn.active {
            background-color: #10b981;
            color: white;
            border-color: #10b981;
        }

        .action-btn.active:hover {
            background-color: #059669;
            border-color: #059669;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background-color: #fef3c7;
            color: #92400e;
        }

        /* Form Steps */
        .form-step,
        .edit-form-step {
            display: none;
        }

        .form-step.active,
        .edit-form-step.active {
            display: block;
        }

        /* Error Messages */
        .error-message {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Input Error State */
        .input-error {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
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

            .brand-text {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .datetime-container {
                display: none;
            }

            .main-content {
                padding: 1rem;
            }

            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }

            .action-btn {
                width: 28px;
                height: 28px;
            }

            .modal-content {
                margin: 0.5rem;
                max-height: 85vh;
            }
        }

        @media (max-width: 640px) {
            .navbar-container {
                padding: 0 0.75rem;
            }

            .main-content {
                padding: 0.75rem;
            }

            .modal-content {
                margin: 0.25rem;
                max-height: 80vh;
            }
        }

        /* Overlay for mobile menu */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            display: none;
            backdrop-filter: blur(4px);
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

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.4);
        }

        /* Pagination Styles */
        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            background-color: white;
            color: #374151;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 0.375rem;
        }

        .pagination-btn:hover:not(:disabled) {
            background-color: #f3f4f6;
            border-color: #9ca3af;
        }

        .pagination-btn.active {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.toggle-slider {
            background-color: #3b82f6;
        }

        input:checked+.toggle-slider:before {
            transform: translateX(30px);
        }

        /* ===== PAYROLL DROPDOWN FIXED STYLES ===== */
        .sidebar-dropdown {
            position: relative;
        }

        #payroll-toggle {
            cursor: pointer;
            display: flex;
            align-items: center;
            width: 100%;
        }

        #payroll-toggle .chevron {
            transition: transform 0.3s ease;
            margin-left: auto;
            font-size: 0.8rem;
        }

        #payroll-toggle .chevron.rotate {
            transform: rotate(180deg);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 1.25rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .dropdown-item:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            padding-left: 1.5rem;
        }

        .dropdown-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: transparent;
            transition: background 0.3s ease;
        }

        .dropdown-item:hover::before {
            background: white;
        }

        .dropdown-item i {
            font-size: 0.6rem;
        }

        /* Animation for dropdown */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Make sure sidebar items don't interfere */
        .sidebar-item {
            display: flex !important;
            align-items: center !important;
            gap: 1rem !important;
            padding: 0.9rem 1.25rem !important;
        }

        /* Fix for mobile sidebar */
        @media (max-width: 1024px) {
            .sidebar-container.active #payroll-dropdown.show {
                display: block !important;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation Header -->
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Left Section -->
            <div class="navbar-left">
                <!-- Mobile Menu Toggle -->
                <button class="mobile-toggle" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>

                <!-- Logo and Brand -->
                <a href="dashboard.php" class="navbar-brand">
                    <img class="brand-logo"
                        src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                        alt="Logo" />
                    <div class="brand-text">
                        <span class="brand-title">HR Management System</span>
                        <span class="brand-subtitle">Paluan Occidental Mindoro</span>
                    </div>
                </a>
            </div>

            <!-- Right Section -->
            <div class="navbar-right">
                <!-- Date & Time -->
                <div class="datetime-container">
                    <div class="datetime-box">
                        <i class="datetime-icon fas fa-calendar-alt"></i>
                        <div class="datetime-text">
                            <span class="datetime-label">Date</span>
                            <span class="datetime-value" id="current-date">Loading...</span>
                        </div>
                    </div>

                    <div class="datetime-box">
                        <i class="datetime-icon fas fa-clock"></i>
                        <div class="datetime-text">
                            <span class="datetime-label">Time</span>
                            <span class="datetime-value" id="current-time">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Sidebar -->
    <div class="sidebar-container" id="sidebar-container">
        <div class="sidebar">
            <div class="sidebar-content">
                <!-- Dashboard -->
                <a href="#dashboard.php" class="sidebar-item active">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard Analytics</span>
                </a>

                <!-- Employees -->
                <a href="./employees/Employee.php" class="sidebar-item ">
                    <i class="fas fa-users"></i>
                    <span>Employees</span>
                </a>

                <!-- Attendance -->
                <a href="./attendance.php" class="sidebar-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>

                <!-- Payroll Dropdown -->
                <a href="#" class="sidebar-item" id="payroll-toggle">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payroll</span>
                    <i class="fas fa-chevron-down chevron ml-auto"></i>
                </a>
                <div class="dropdown-menu" id="payroll-dropdown">
                    <a href="../Payrollmanagement/contractualpayrolltable1.php" class="dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Contractual
                    </a>
                    <a href="../Payrollmanagement/joboerderpayrolltable1.php" class="dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Job Order
                    </a>
                    <a href="../Payrollmanagement/permanentpayrolltable1.php" class="dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Permanent
                    </a>
                </div>

                <!-- Reports -->
                <a href="./paysliphistory.php" class="sidebar-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                </a>

                <!-- Settings -->
                <a href="./settings.php" class="sidebar-item">
                    <i class="fas fa-sliders-h"></i>
                    <span>Settings</span>
                </a>

                <!-- Logout -->
                <a href="?logout=true" class="sidebar-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <div class="text-center text-white/60 text-sm ">
                    <p>HRMS v2.0</p>
                    <p class="text-xs mt-1">© 2024 Paluan LGU</p>
                </div>
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
                <div class="modal-body">
                    <!-- Success/Error Messages -->
                    <?php if (isset($_SESSION['profile_update_success'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php
                            echo htmlspecialchars($_SESSION['profile_update_success'], ENT_QUOTES, 'UTF-8');
                            unset($_SESSION['profile_update_success']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['profile_update_error'])): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php
                            echo htmlspecialchars($_SESSION['profile_update_error'], ENT_QUOTES, 'UTF-8');
                            unset($_SESSION['profile_update_error']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Profile Picture Section -->
                    <div class="profile-picture-section">
                        <div class="profile-avatar-container">
                            <img src="<?php echo htmlspecialchars($user_avatar, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="Profile Picture" class="profile-avatar" id="profile-avatar-preview">
                            <button type="button" class="change-avatar-btn" id="change-avatar-btn"
                                aria-label="Change profile picture">
                                <i class="fas fa-camera"></i>
                            </button>
                            <input type="file" name="profile_picture" id="profile_picture" class="avatar-input"
                                accept="image/*">
                        </div>
                        <div class="file-info">
                            <p>Maximum file size: 2MB<br>Allowed formats: JPG, PNG, GIF</p>
                        </div>
                        <div id="preview-container" class="preview-container" style="display: none;">
                            <img id="image-preview" class="preview-image" alt="Image Preview">
                        </div>
                    </div>

                    <!-- User Information Grid -->
                    <div class="user-info-grid">
                        <div class="user-info-item">
                            <label>Admin ID</label>
                            <span><?php echo htmlspecialchars($user_id, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="user-info-item">
                            <label>Role</label>
                            <span><?php echo htmlspecialchars($user_role, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="user-info-item">
                            <label>Last Login</label>
                            <span><?php echo date('F j, Y, g:i a', $login_time); ?></span>
                        </div>
                        <div class="user-info-item">
                            <label>Status</label>
                            <span style="color: var(--success); font-weight: 700;">Active</span>
                        </div>
                    </div>

                    <!-- Form Fields -->
                    <div class="profile-info">
                        <div class="form-group">
                            <label for="full_name"><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-control"
                                value="<?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" id="email" name="email" class="form-control"
                                value="<?php echo htmlspecialchars($user_email, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
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
    <!-- IMPROVED MAIN CONTENT -->
    <main class="main-content" role="main">
        <div class="container">
            <!-- Enhanced Dashboard Header -->
            <div class="dashboard-header fade-in">
                <h1 class="dashboard-title">HR Analytics Dashboard</h1>
                <div class="dashboard-subtitle">
                    <p>Welcome back, <strong><?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?></strong>!
                        Get insights into your HR metrics, track performance, and make data-driven decisions.</p>
                </div>
                <div class="welcome-card">
                    <div class="welcome-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="welcome-content">
                        <h4>Last login: <?php echo date('F j, Y, g:i a', $login_time); ?></h4>
                        <p>Monitor real-time HR metrics, track attendance trends, and manage payroll efficiently with
                            our comprehensive dashboard.</p>
                    </div>
                </div>
            </div>

            <!-- Enhanced Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card primary slide-up">
                    <div class="stat-header">
                        <div class="stat-icon-wrapper">
                            <div class="stat-icon primary">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-arrow-up"></i>
                            <span>12.5%</span>
                        </div>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo htmlspecialchars($total_employees, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="stat-label">
                            <i class="fas fa-user-friends"></i>
                            Total Employees
                        </p>
                        <p class="stat-detail">Active workforce across all departments and employment types</p>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" style="width: 85%"></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card success slide-up">
                    <div class="stat-header">
                        <div class="stat-icon-wrapper">
                            <div class="stat-icon success">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-arrow-up"></i>
                            <span>8.3%</span>
                        </div>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo htmlspecialchars($attendance_rate, ENT_QUOTES, 'UTF-8'); ?>%</h3>
                        <p class="stat-label">
                            <i class="fas fa-clock"></i>
                            Today's Attendance
                        </p>
                        <p class="stat-detail"><?php echo htmlspecialchars($today_attendance, ENT_QUOTES, 'UTF-8'); ?>
                            employees present today</p>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" style="width: <?php echo $attendance_rate; ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card warning slide-up">
                    <div class="stat-header">
                        <div class="stat-icon-wrapper">
                            <div class="stat-icon warning">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                        </div>
                        <div class="stat-trend trend-down">
                            <i class="fas fa-arrow-down"></i>
                            <span>3.2%</span>
                        </div>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo htmlspecialchars($payroll_pending, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="stat-label">
                            <i class="fas fa-exclamation-circle"></i>
                            Pending Payroll
                        </p>
                        <p class="stat-detail">Requiring processing for the current month</p>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" style="width: <?php echo $payroll_pending_percent; ?>%">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stat-card danger slide-up">
                    <div class="stat-header">
                        <div class="stat-icon-wrapper">
                            <div class="stat-icon danger">
                                <i class="fas fa-file-contract"></i>
                            </div>
                        </div>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-arrow-up"></i>
                            <span>2.1%</span>
                        </div>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo htmlspecialchars($cont_employees_count, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="stat-label">
                            <i class="fas fa-hourglass-half"></i>
                            Contractual Staff
                        </p>
                        <p class="stat-detail">Requiring contract renewal review this quarter</p>
                        <div class="stat-progress">
                            <div class="stat-progress-bar"
                                style="width: <?php echo $total_employees > 0 ? round(($cont_employees_count / $total_employees) * 100) : 0; ?>%">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Charts Section -->
            <div class="charts-grid">
                <!-- Enhanced Attendance Chart -->
                <div class="chart-card fade-in">
                    <div class="chart-header">
                        <div>
                            <h3 class="chart-title">
                                <i class="fas fa-chart-line"></i>
                                Weekly Attendance Trend
                            </h3>
                            <p class="chart-subtitle">Daily attendance for the past 7 days across all employee types</p>
                        </div>
                        <div class="chart-actions">
                            <button class="chart-action-btn active">Week</button>
                            <button class="chart-action-btn">Month</button>
                            <button class="chart-action-btn">Year</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <div id="attendance-chart"></div>
                    </div>
                    <div class="type-stats">
                        <?php foreach ($employee_types as $type => $count):
                            $percentage = $total_employees > 0 ? round(($count / $total_employees) * 100) : 0;
                            $color_class = $type === 'Permanent' ? 'primary' : ($type === 'Contractual' ? 'warning' : 'info');
                            ?>
                            <div class="type-stat <?php echo $color_class; ?>">
                                <h4><?php echo htmlspecialchars($count, ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p><?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?> Employees</p>
                                <div class="percentage"><?php echo htmlspecialchars($percentage, ENT_QUOTES, 'UTF-8'); ?>%
                                    of total</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Enhanced Department Distribution Chart -->
                <div class="chart-card fade-in">
                    <div class="chart-header">
                        <div>
                            <h3 class="chart-title">
                                <i class="fas fa-sitemap"></i>
                                Department Distribution
                            </h3>
                            <p class="chart-subtitle">Employee breakdown by department with employment type details</p>
                        </div>
                        <div class="chart-actions">
                            <button class="chart-action-btn active" data-chart-type="donut">Donut</button>
                            <button class="chart-action-btn" data-chart-type="bar">Bar</button>
                            <button class="chart-action-btn" data-chart-type="treemap">Tree</button>
                        </div>
                    </div>

                    <!-- Chart Controls -->
                    <div class="chart-controls">
                        <div class="control-group">
                            <label for="dept-sort">
                                <i class="fas fa-sort-amount-down"></i>
                                Sort by:
                            </label>
                            <select id="dept-sort" class="form-control-sm">
                                <option value="count">Count (High to Low)</option>
                                <option value="name">Name (A to Z)</option>
                                <option value="type">Employment Type</option>
                            </select>
                        </div>
                        <div class="control-group">
                            <label for="dept-limit">
                                <i class="fas fa-filter"></i>
                                Show:
                            </label>
                            <select id="dept-limit" class="form-control-sm">
                                <option value="5">Top 5</option>
                                <option value="10" selected>Top 10</option>
                                <option value="15">Top 15</option>
                                <option value="0">All</option>
                            </select>
                        </div>
                        <button class="chart-action-btn" id="dept-download">
                            <i class="fas fa-download"></i>
                            Export
                        </button>
                    </div>

                    <!-- Chart Container -->
                    <div class="chart-container">
                        <div id="department-chart"></div>
                    </div>

                    <!-- Department Details Panel -->
                    <div class="details-panel" id="dept-details">
                        <div class="details-header">
                            <h4>
                                <i class="fas fa-info-circle"></i>
                                Department Details
                            </h4>
                            <button class="details-close" id="dept-details-close">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="details-content">
                            <div class="dept-summary">
                                <div class="dept-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="dept-info">
                                    <h5 id="dept-name">Select a Department</h5>
                                    <p id="dept-total">Total Employees: 0</p>
                                    <p id="dept-percentage">Percentage: 0%</p>
                                </div>
                            </div>
                            <div class="dept-breakdown" id="dept-breakdown">
                                <p class="no-data">Select a department from the chart to view breakdown</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Detailed Stats & Activity -->
            <div class="charts-grid">
                <!-- Enhanced Payroll Statistics -->
                <div class="chart-card fade-in">
                    <div class="chart-header">
                        <h3 class="chart-title">
                            <i class="fas fa-money-bill-wave"></i>
                            Payroll Processing Status
                        </h3>
                        <p class="chart-subtitle">Current month payroll overview across all employee categories</p>
                    </div>
                    <div class="payroll-stats">
                        <div class="payroll-stat processed">
                            <div class="payroll-stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4><?php echo htmlspecialchars($payroll_processed, ENT_QUOTES, 'UTF-8'); ?></h4>
                            <p>Processed</p>
                            <small><?php echo htmlspecialchars($payroll_processed_percent, ENT_QUOTES, 'UTF-8'); ?>% of
                                total payroll processed</small>
                        </div>
                        <div class="payroll-stat pending">
                            <div class="payroll-stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h4><?php echo htmlspecialchars($payroll_pending, ENT_QUOTES, 'UTF-8'); ?></h4>
                            <p>Pending</p>
                            <small><?php echo htmlspecialchars($payroll_pending_percent, ENT_QUOTES, 'UTF-8'); ?>% of
                                total payroll pending</small>
                        </div>
                    </div>
                    <div class="summary-cards">
                        <div class="summary-card">
                            <div class="summary-icon" style="background: var(--gradient-primary);">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="summary-content">
                                <h4><?php echo htmlspecialchars($perm_employees_count, ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p>Permanent Staff</p>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon" style="background: var(--gradient-warning);">
                                <i class="fas fa-file-signature"></i>
                            </div>
                            <div class="summary-content">
                                <h4><?php echo htmlspecialchars($cont_employees_count, ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p>Contractual Staff</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Recent Activity -->
                <div class="activity-card fade-in">
                    <div class="chart-header">
                        <h3 class="chart-title">
                            <i class="fas fa-history"></i>
                            Recent Activity
                        </h3>
                        <p class="chart-subtitle">Latest updates and actions across the HR system</p>
                    </div>
                    <ul class="activity-list">
                        <?php foreach ($recent_activities as $activity):
                            $icon_class = 'fas fa-' . $activity['icon'];
                            $time_ago = time_elapsed_string($activity['time']);
                            ?>
                            <li class="activity-item">
                                <div
                                    class="activity-icon <?php echo htmlspecialchars($activity['icon_color'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="<?php echo htmlspecialchars($icon_class, ENT_QUOTES, 'UTF-8'); ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <h4><?php echo htmlspecialchars($activity['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <p><?php echo htmlspecialchars($activity['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <div class="activity-time">
                                        <i class="far fa-clock"></i>
                                        <?php echo htmlspecialchars($time_ago, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Enhanced Quick Actions -->
            <div class="quick-actions-section fade-in">
                <div class="section-header">
                    <div>
                        <h3 class="section-title">
                            <i class="fas fa-bolt"></i>
                            Quick Actions
                        </h3>
                        <p class="section-subtitle">Frequently used actions for efficient HR management</p>
                    </div>
                </div>
                <div class="quick-actions">
                    <a href="./employees/Employee.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="action-text">
                            <h4>Add Employee</h4>
                            <p>Register new employee to the system</p>
                        </div>
                    </a>
                    <a href="attendance.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="action-text">
                            <h4>Mark Attendance</h4>
                            <p>Record daily attendance for all employees</p>
                        </div>
                    </a>
                    <a href="Payrollmanagement/contractualpayrolltable1.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <div class="action-text">
                            <h4>Process Payroll</h4>
                            <p>Generate and process payroll reports</p>
                        </div>
                    </a>
                    <a href="paysliphistory.php" class="quick-action">
                        <div class="action-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="action-text">
                            <h4>View Reports</h4>
                            <p>Generate comprehensive HR reports</p>
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Mobile sidebar toggle
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebarContainer = document.getElementById('sidebar-container');
            const overlay = document.getElementById('overlay');

            function toggleSidebar() {
                const isActive = sidebarContainer.classList.toggle('active');
                overlay.classList.toggle('active');
                document.body.style.overflow = isActive ? 'hidden' : '';
                sidebarToggle.setAttribute('aria-expanded', isActive);
                sidebarToggle.setAttribute('aria-label', isActive ? 'Close menu' : 'Open menu');
            }

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', toggleSidebar);
                sidebarToggle.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggleSidebar();
                    }
                });
            }

            if (overlay) {
                overlay.addEventListener('click', toggleSidebar);
            }

            // User dropdown functionality
            const userMenuButton = document.getElementById('user-menu-button');
            const userDropdown = document.getElementById('user-dropdown');

            if (userMenuButton && userDropdown) {
                userMenuButton.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const isActive = userDropdown.classList.toggle('active');
                    userMenuButton.setAttribute('aria-expanded', isActive);
                    userDropdown.setAttribute('aria-hidden', !isActive);
                });

                userMenuButton.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        const isActive = userDropdown.classList.toggle('active');
                        userMenuButton.setAttribute('aria-expanded', isActive);
                        userDropdown.setAttribute('aria-hidden', !isActive);
                    }
                });

                document.addEventListener('click', function () {
                    userDropdown.classList.remove('active');
                    userMenuButton.setAttribute('aria-expanded', false);
                    userDropdown.setAttribute('aria-hidden', true);
                });

                userDropdown.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
            }

            // Profile Modal Functionality
            const profileModal = document.getElementById('profile-modal');
            const openProfileModalBtn = document.getElementById('open-profile-modal');
            const closeProfileModalBtn = document.getElementById('close-profile-modal');
            const cancelProfileChangesBtn = document.getElementById('cancel-profile-changes');
            const profileForm = document.getElementById('profile-form');
            const profilePictureInput = document.getElementById('profile_picture');
            const changeAvatarBtn = document.getElementById('change-avatar-btn');
            const profileAvatarPreview = document.getElementById('profile-avatar-preview');
            const previewContainer = document.getElementById('preview-container');
            const imagePreview = document.getElementById('image-preview');

            // Open profile modal
            if (openProfileModalBtn) {
                openProfileModalBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    profileModal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                    userDropdown.classList.remove('active');
                });
            }

            // Close profile modal
            function closeProfileModal() {
                profileModal.classList.remove('active');
                document.body.style.overflow = '';
                // Reset form preview
                if (previewContainer) previewContainer.style.display = 'none';
                if (profileForm) profileForm.reset();
            }

            if (closeProfileModalBtn) {
                closeProfileModalBtn.addEventListener('click', closeProfileModal);
            }

            if (cancelProfileChangesBtn) {
                cancelProfileChangesBtn.addEventListener('click', closeProfileModal);
            }

            // Close modal when clicking outside
            profileModal.addEventListener('click', function (e) {
                if (e.target === profileModal) {
                    closeProfileModal();
                }
            });

            // Handle profile picture change
            if (changeAvatarBtn) {
                changeAvatarBtn.addEventListener('click', function () {
                    profilePictureInput.click();
                });
            }

            // Handle file input change for avatar
            if (profilePictureInput) {
                profilePictureInput.addEventListener('change', function (e) {
                    if (e.target.files && e.target.files[0]) {
                        const file = e.target.files[0];
                        const reader = new FileReader();

                        reader.onload = function (event) {
                            profileAvatarPreview.src = event.target.result;
                        };

                        reader.readAsDataURL(file);
                    }
                });
            }

            // Validate file size and type
            if (profileForm) {
                profileForm.addEventListener('submit', function (e) {
                    const fileInput = profilePictureInput;
                    if (fileInput.files.length > 0) {
                        const file = fileInput.files[0];
                        const maxSize = 2 * 1024 * 1024; // 2MB
                        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

                        if (file.size > maxSize) {
                            e.preventDefault();
                            alert('File size exceeds 2MB limit. Please choose a smaller file.');
                            return false;
                        }

                        if (!allowedTypes.includes(file.type)) {
                            e.preventDefault();
                            alert('Please select a valid image file (JPG, PNG, or GIF).');
                            return false;
                        }
                    }
                    return true;
                });
            }

            // Update date and time
            function updateDateTime() {
                const now = new Date();
                const dateOptions = {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                };
                const timeOptions = {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                };

                const dateElement = document.getElementById('current-date');
                const timeElement = document.getElementById('current-time');

                if (dateElement) {
                    dateElement.textContent = now.toLocaleDateString('en-US', dateOptions);
                }
                if (timeElement) {
                    timeElement.textContent = now.toLocaleTimeString('en-US', timeOptions);
                }
            }

            updateDateTime();
            setInterval(updateDateTime, 1000);

            // Scroll to top functionality
            const scrollTopBtn = document.getElementById('scrollTop');

            if (scrollTopBtn) {
                window.addEventListener('scroll', function () {
                    if (window.pageYOffset > 300) {
                        scrollTopBtn.classList.add('show');
                    } else {
                        scrollTopBtn.classList.remove('show');
                    }
                });

                scrollTopBtn.addEventListener('click', function () {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });

                // Keyboard accessibility for scroll top
                scrollTopBtn.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    }
                });
            }

            // Enhanced Attendance Chart with better sizing
            try {
                const attendanceChart = new ApexCharts(document.querySelector("#attendance-chart"), {
                    series: [{
                        name: 'Attendance',
                        data: <?php echo $attendance_series; ?>
                    }],
                    chart: {
                        type: 'area',
                        height: 350,
                        width: '100%',
                        toolbar: {
                            show: true,
                            tools: {
                                download: true,
                                selection: true,
                                zoom: true,
                                zoomin: true,
                                zoomout: true,
                                pan: true,
                                reset: true
                            }
                        },
                        animations: {
                            enabled: true,
                            easing: 'easeinout',
                            speed: 800
                        }
                    },
                    stroke: {
                        width: 4,
                        curve: 'smooth'
                    },
                    colors: ['#3b82f6'],
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.8,
                            opacityTo: 0.3,
                            stops: [0, 90, 100]
                        }
                    },
                    markers: {
                        size: 7,
                        colors: ['#fff'],
                        strokeColors: '#3b82f6',
                        strokeWidth: 3,
                        hover: {
                            size: 10
                        }
                    },
                    xaxis: {
                        categories: <?php echo $attendance_labels; ?>,
                        labels: {
                            style: {
                                colors: '#6b7280',
                                fontSize: '13px',
                                fontWeight: 600
                            }
                        }
                    },
                    yaxis: {
                        min: 0,
                        max: <?php echo $total_employees > 0 ? $total_employees : 100; ?>,
                        labels: {
                            style: {
                                colors: '#6b7280',
                                fontSize: '13px',
                                fontWeight: 600
                            },
                            formatter: function (val) {
                                return Math.round(val);
                            }
                        },
                        title: {
                            text: 'Number of Employees',
                            style: {
                                color: '#6b7280',
                                fontSize: '14px',
                                fontWeight: 700
                            }
                        }
                    },
                    grid: {
                        borderColor: '#e5e7eb',
                        strokeDashArray: 4,
                        padding: {
                            top: 15,
                            right: 15,
                            bottom: 15,
                            left: 15
                        }
                    },
                    tooltip: {
                        y: {
                            formatter: function (val) {
                                const total = <?php echo $total_employees > 0 ? $total_employees : 100; ?>;
                                const percentage = Math.round((val / total) * 100);
                                return `<strong>${val} employees</strong><br>${percentage}% of total`;
                            }
                        },
                        style: {
                            fontSize: '14px'
                        },
                        theme: 'dark',
                        marker: {
                            show: true
                        }
                    },
                    responsive: [{
                        breakpoint: 1200,
                        options: {
                            chart: {
                                height: 320
                            }
                        }
                    },
                    {
                        breakpoint: 992,
                        options: {
                            chart: {
                                height: 300
                            },
                            markers: {
                                size: 6
                            }
                        }
                    },
                    {
                        breakpoint: 768,
                        options: {
                            chart: {
                                height: 280
                            },
                            markers: {
                                size: 5
                            },
                            stroke: {
                                width: 3
                            }
                        }
                    },
                    {
                        breakpoint: 576,
                        options: {
                            chart: {
                                height: 250
                            },
                            markers: {
                                size: 4
                            },
                            stroke: {
                                width: 2
                            },
                            xaxis: {
                                labels: {
                                    style: {
                                        fontSize: '11px'
                                    }
                                }
                            },
                            yaxis: {
                                labels: {
                                    style: {
                                        fontSize: '11px'
                                    }
                                }
                            }
                        }
                    }
                    ]
                });
                attendanceChart.render();
            } catch (error) {
                console.error('Error rendering attendance chart:', error);
            }

            // Enhanced 3D Department Distribution Chart with interactivity
            try {
                let currentChartType = 'donut';
                let departmentChart = null;

                // Department data from PHP
                const deptSeries = <?php echo $dept_series; ?>;
                const deptLabels = <?php echo $dept_labels; ?>;
                const deptColors = <?php echo $dept_colors_json; ?>;
                const totalEmployees = <?php echo $total_employees > 0 ? $total_employees : 100; ?>;

                // Create initial chart
                function createDepartmentChart(type = 'donut') {
                    const options = {
                        series: deptSeries,
                        chart: {
                            type: type === 'bar' ? 'bar' : 'donut',
                            height: 420,
                            width: '100%',
                            animations: {
                                enabled: true,
                                easing: 'easeinout',
                                speed: 800
                            },
                            events: {
                                dataPointSelection: function (event, chartContext, config) {
                                    const index = config.dataPointIndex;
                                    const deptName = deptLabels[index];
                                    const deptCount = deptSeries[index];
                                    const percentage = Math.round((deptCount / totalEmployees) * 100);

                                    // Update details panel
                                    document.getElementById('dept-name').textContent = deptName;
                                    document.getElementById('dept-total').textContent = `Total Employees: ${deptCount}`;
                                    document.getElementById('dept-percentage').textContent = `Percentage: ${percentage}%`;

                                    // Update breakdown (mock data - replace with actual from PHP if available)
                                    const breakdownHtml = `
                                        <div class="breakdown-item">
                                            <div class="type">
                                                <i class="fas fa-user-tie"></i>
                                                <span>Permanent</span>
                                            </div>
                                            <div class="count">${Math.round(deptCount * 0.6)}</div>
                                        </div>
                                        <div class="breakdown-item">
                                            <div class="type">
                                                <i class="fas fa-file-contract"></i>
                                                <span>Contractual</span>
                                            </div>
                                            <div class="count">${Math.round(deptCount * 0.3)}</div>
                                        </div>
                                        <div class="breakdown-item">
                                            <div class="type">
                                                <i class="fas fa-tasks"></i>
                                                <span>Job Order</span>
                                            </div>
                                            <div class="count">${Math.round(deptCount * 0.1)}</div>
                                        </div>
                                    `;
                                    document.getElementById('dept-breakdown').innerHTML = breakdownHtml;

                                    // Show details panel
                                    document.getElementById('dept-details').classList.add('active');
                                }
                            },
                            toolbar: {
                                show: true,
                                tools: {
                                    download: true,
                                    selection: false,
                                    zoom: false,
                                    zoomin: false,
                                    zoomout: false,
                                    pan: false,
                                    reset: true
                                }
                            }
                        },
                        colors: deptColors,
                        plotOptions: type === 'bar' ? {
                            bar: {
                                horizontal: true,
                                borderRadius: 8,
                                borderRadiusApplication: 'end',
                                columnWidth: '70%',
                                distributed: true
                            }
                        } : {
                            pie: {
                                donut: {
                                    size: '65%',
                                    background: 'transparent',
                                    labels: {
                                        show: true,
                                        name: {
                                            show: true,
                                            fontSize: '14px',
                                            fontFamily: 'Inter, sans-serif',
                                            fontWeight: 600,
                                            color: '#374151',
                                            offsetY: -10
                                        },
                                        value: {
                                            show: true,
                                            fontSize: '24px',
                                            fontFamily: 'Inter, sans-serif',
                                            fontWeight: 800,
                                            color: '#1f2937',
                                            offsetY: 8,
                                            formatter: function (val) {
                                                const percent = Math.round((val / totalEmployees) * 100);
                                                return `${val} (${percent}%)`;
                                            }
                                        },
                                        total: {
                                            show: true,
                                            showAlways: true,
                                            label: 'Total',
                                            fontSize: '16px',
                                            fontFamily: 'Inter, sans-serif',
                                            fontWeight: 700,
                                            color: '#1f2937',
                                            formatter: function (w) {
                                                return totalEmployees;
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        labels: deptLabels,
                        dataLabels: {
                            enabled: type === 'donut',
                            style: {
                                fontSize: '11px',
                                fontFamily: 'Inter, sans-serif',
                                fontWeight: 600,
                                colors: ['#fff']
                            },
                            dropShadow: {
                                enabled: true,
                                top: 1,
                                left: 1,
                                blur: 2,
                                color: '#000',
                                opacity: 0.45
                            },
                            formatter: function (val, opts) {
                                return opts.w.config.labels[opts.seriesIndex];
                            }
                        },
                        legend: {
                            position: 'bottom',
                            horizontalAlign: 'center',
                            fontSize: '12px',
                            fontFamily: 'Inter, sans-serif',
                            fontWeight: 500,
                            labels: {
                                colors: '#374151',
                                useSeriesColors: false
                            },
                            markers: {
                                width: 12,
                                height: 12,
                                radius: 6,
                                offsetX: -4,
                                offsetY: 1
                            },
                            itemMargin: {
                                horizontal: 12,
                                vertical: 6
                            },
                            onItemClick: {
                                toggleDataSeries: true
                            },
                            onItemHover: {
                                highlightDataSeries: true
                            }
                        },
                        tooltip: {
                            enabled: true,
                            y: {
                                formatter: function (val, opts) {
                                    const percentage = Math.round((val / totalEmployees) * 100);
                                    return `<div style="padding: 5px 0;">
                                        <div style="font-weight: 700; margin-bottom: 5px;">${deptLabels[opts.seriesIndex]}</div>
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span>Employees:</span>
                                            <span style="font-weight: 800; font-size: 1.1em;">${val}</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span>Percentage:</span>
                                            <span style="font-weight: 700; color: #3b82f6;">${percentage}%</span>
                                        </div>
                                    </div>`;
                                }
                            },
                            style: {
                                fontSize: '13px',
                                fontFamily: 'Inter, sans-serif'
                            },
                            theme: 'light',
                            marker: {
                                show: true
                            },
                            custom: function ({ series, seriesIndex, dataPointIndex, w }) {
                                const data = w.config.series[dataPointIndex];
                                const label = w.config.labels[dataPointIndex];
                                const percentage = Math.round((data / totalEmployees) * 100);

                                return `<div class="custom-tooltip">
                                    <div class="tooltip-header">${label}</div>
                                    <div class="tooltip-body">
                                        <div class="tooltip-row">
                                            <span>Employees:</span>
                                            <strong>${data}</strong>
                                        </div>
                                        <div class="tooltip-row">
                                            <span>Percentage:</span>
                                            <strong style="color: #3b82f6;">${percentage}%</strong>
                                        </div>
                                    </div>
                                </div>`;
                            }
                        },
                        stroke: type === 'bar' ? {
                            width: 0
                        } : {
                            width: 3,
                            colors: ['#fff']
                        },
                        states: {
                            hover: {
                                filter: {
                                    type: 'lighten',
                                    value: 0.15
                                }
                            },
                            active: {
                                filter: {
                                    type: 'darken',
                                    value: 0.25
                                }
                            }
                        },
                        grid: type === 'bar' ? {
                            borderColor: '#e5e7eb',
                            strokeDashArray: 4,
                            padding: {
                                top: 0,
                                right: 20,
                                bottom: 0,
                                left: 20
                            }
                        } : {},
                        xaxis: type === 'bar' ? {
                            categories: deptLabels,
                            labels: {
                                style: {
                                    colors: '#6b7280',
                                    fontSize: '12px',
                                    fontWeight: 600
                                }
                            },
                            title: {
                                text: 'Number of Employees',
                                style: {
                                    color: '#6b7280',
                                    fontSize: '13px',
                                    fontWeight: 700
                                }
                            }
                        } : {},
                        yaxis: type === 'bar' ? {
                            labels: {
                                style: {
                                    colors: '#6b7280',
                                    fontSize: '12px',
                                    fontWeight: 600
                                }
                            }
                        } : {},
                        responsive: [{
                            breakpoint: 1200,
                            options: {
                                chart: {
                                    height: 380
                                },
                                plotOptions: {
                                    pie: {
                                        donut: {
                                            size: '60%'
                                        }
                                    }
                                }
                            }
                        },
                        {
                            breakpoint: 992,
                            options: {
                                chart: {
                                    height: 350
                                },
                                plotOptions: {
                                    pie: {
                                        donut: {
                                            size: '55%'
                                        }
                                    }
                                },
                                legend: {
                                    fontSize: '11px'
                                }
                            }
                        },
                        {
                            breakpoint: 768,
                            options: {
                                chart: {
                                    height: 320
                                },
                                plotOptions: {
                                    pie: {
                                        donut: {
                                            size: '50%'
                                        }
                                    }
                                },
                                legend: {
                                    fontSize: '10px',
                                    position: 'bottom',
                                    horizontalAlign: 'center'
                                }
                            }
                        },
                        {
                            breakpoint: 576,
                            options: {
                                chart: {
                                    height: 280
                                },
                                plotOptions: {
                                    pie: {
                                        donut: {
                                            size: '45%',
                                            labels: {
                                                name: {
                                                    fontSize: '12px'
                                                },
                                                value: {
                                                    fontSize: '20px'
                                                },
                                                total: {
                                                    fontSize: '14px'
                                                }
                                            }
                                        }
                                    }
                                },
                                legend: {
                                    show: false
                                },
                                dataLabels: {
                                    enabled: false
                                }
                            }
                        }]
                    };

                    // Destroy existing chart if it exists
                    if (departmentChart) {
                        departmentChart.destroy();
                    }

                    // Create new chart
                    departmentChart = new ApexCharts(document.querySelector("#department-chart"), options);
                    departmentChart.render();
                }

                // Create initial chart
                createDepartmentChart();

                // Chart type toggle
                document.querySelectorAll('.chart-action-btn[data-chart-type]').forEach(button => {
                    button.addEventListener('click', function () {
                        document.querySelectorAll('.chart-action-btn[data-chart-type]').forEach(btn => {
                            btn.classList.remove('active');
                        });
                        this.classList.add('active');

                        const type = this.getAttribute('data-chart-type');
                        currentChartType = type;

                        // Add loading animation
                        const chartCard = this.closest('.chart-card');
                        chartCard.classList.add('loading');

                        setTimeout(() => {
                            createDepartmentChart(type);
                            chartCard.classList.remove('loading');
                        }, 300);
                    });
                });

                // Sort functionality
                document.getElementById('dept-sort').addEventListener('change', function () {
                    // Implement sorting logic based on selected option
                    const sortBy = this.value;
                    const chartCard = this.closest('.chart-card');
                    chartCard.classList.add('loading');

                    setTimeout(() => {
                        // For demo purposes - in real implementation, you would sort the data
                        departmentChart.updateOptions({
                            chart: {
                                animations: {
                                    enabled: false
                                }
                            }
                        });

                        setTimeout(() => {
                            departmentChart.updateOptions({
                                chart: {
                                    animations: {
                                        enabled: true
                                    }
                                }
                            });
                            chartCard.classList.remove('loading');
                        }, 100);
                    }, 300);
                });

                // Limit functionality
                document.getElementById('dept-limit').addEventListener('change', function () {
                    // Implement limit logic based on selected value
                    const limit = parseInt(this.value);
                    const chartCard = this.closest('.chart-card');
                    chartCard.classList.add('loading');

                    setTimeout(() => {
                        // For demo purposes - in real implementation, you would filter the data
                        chartCard.classList.remove('loading');
                    }, 500);
                });

                // Export functionality
                document.getElementById('dept-download').addEventListener('click', function () {
                    if (departmentChart) {
                        departmentChart.dataURI().then(({ imgURI, blob }) => {
                            const link = document.createElement('a');
                            link.href = imgURI;
                            link.download = `department-distribution-${new Date().toISOString().split('T')[0]}.png`;
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        });
                    }
                });

                // Close details panel
                document.getElementById('dept-details-close').addEventListener('click', function () {
                    document.getElementById('dept-details').classList.remove('active');
                });

            } catch (error) {
                console.error('Error rendering department chart:', error);
            }

            // Chart period toggle functionality
            const chartButtons = document.querySelectorAll('.chart-action-btn');
            chartButtons.forEach(button => {
                button.addEventListener('click', function () {
                    chartButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    // Add loading animation
                    const chartCard = this.closest('.chart-card');
                    if (chartCard) {
                        chartCard.classList.add('loading');
                        setTimeout(() => {
                            chartCard.classList.remove('loading');
                        }, 600);
                    }
                });
            });

            // Handle window resize with debounce
            let resizeTimeout;
            window.addEventListener('resize', function () {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    try {
                        // Re-render charts on resize
                        const attendanceChartElement = document.querySelector("#attendance-chart");
                        const departmentChartElement = document.querySelector("#department-chart");

                        if (attendanceChartElement && attendanceChartElement.__apex_chart) {
                            attendanceChartElement.__apex_chart.update();
                        }
                        if (departmentChartElement && departmentChartElement.__apex_chart) {
                            departmentChartElement.__apex_chart.update();
                        }
                    } catch (error) {
                        console.error('Error updating charts on resize:', error);
                    }
                }, 250);
            });

            // Initialize animations for elements when they come into view
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observe all animated elements
            document.querySelectorAll('.fade-in, .slide-up').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(el);
            });

            // Animate progress bars
            document.querySelectorAll('.stat-progress-bar').forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 300);
            });

            // Keyboard navigation
            document.addEventListener('keydown', function (e) {
                // Close sidebar with Escape key
                if (e.key === 'Escape') {
                    if (sidebarContainer.classList.contains('active')) {
                        toggleSidebar();
                    }
                    if (profileModal.classList.contains('active')) {
                        closeProfileModal();
                    }
                    if (userDropdown.classList.contains('active')) {
                        userDropdown.classList.remove('active');
                        userMenuButton.setAttribute('aria-expanded', false);
                        userDropdown.setAttribute('aria-hidden', true);
                    }
                }
            });

            // Add hover effect to quick actions
            document.querySelectorAll('.quick-action').forEach(action => {
                action.addEventListener('mouseenter', function () {
                    const icon = this.querySelector('.action-icon');
                    icon.style.transform = 'scale(1.1) rotate(5deg)';
                });

                action.addEventListener('mouseleave', function () {
                    const icon = this.querySelector('.action-icon');
                    icon.style.transform = 'scale(1) rotate(0deg)';
                });
            });

            // JavaScript helper functions
            function formatDepartmentName(name) {
                if (name.length > 20) {
                    return name.substring(0, 20) + '...';
                }
                return name;
            }

            function getTypeIcon(type) {
                const icons = {
                    'permanent': 'user-tie',
                    'contractofservice': 'file-contract',
                    'job_order': 'tasks',
                    'Permanent': 'user-tie',
                    'Contractual': 'file-contract',
                    'Job Order': 'tasks'
                };
                return icons[type] || 'user';
            }

            function getTypeColor(type) {
                const colors = {
                    'permanent': '#3b82f6',
                    'contractofservice': '#f59e0b',
                    'job_order': '#10b981',
                    'Permanent': '#3b82f6',
                    'Contractual': '#f59e0b',
                    'Job Order': '#10b981'
                };
                return colors[type] || '#6b7280';
            }
        });

    </script>
    <script>
        // Payroll Dropdown Functionality
        document.addEventListener('DOMContentLoaded', function () {
            // Payroll dropdown in sidebar
            const payrollToggle = document.getElementById('payroll-toggle');
            const payrollDropdown = document.getElementById('payroll-dropdown');

            if (payrollToggle && payrollDropdown) {
                payrollToggle.addEventListener('click', function (e) {
                    e.preventDefault();
                    const isShowing = payrollDropdown.classList.toggle('show');
                    this.querySelector('.chevron').classList.toggle('rotate');
                    this.setAttribute('aria-expanded', isShowing);
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function (e) {
                    if (!payrollToggle.contains(e.target) && !payrollDropdown.contains(e.target)) {
                        payrollDropdown.classList.remove('show');
                        const chevron = payrollToggle.querySelector('.chevron');
                        if (chevron) {
                            chevron.classList.remove('rotate');
                        }
                        payrollToggle.setAttribute('aria-expanded', false);
                    }
                });
            }

            // Rest of your existing JavaScript...
        });
        // Payroll dropdown in sidebar
        const payrollToggle = document.getElementById('payroll-toggle');
        const payrollDropdown = document.getElementById('payroll-dropdown');

        if (payrollToggle) {
            payrollToggle.addEventListener('click', function (e) {
                e.preventDefault();
                const isShowing = payrollDropdown.classList.toggle('show');
                this.querySelector('.chevron').classList.toggle('rotate');
                this.setAttribute('aria-expanded', isShowing);
            });
        }
        // Payroll Dropdown Functionality
        document.addEventListener('DOMContentLoaded', function () {
            const payrollToggle = document.getElementById('payroll-toggle');
            const payrollDropdown = document.getElementById('payroll-dropdown');

            if (payrollToggle && payrollDropdown) {
                payrollToggle.addEventListener('click', function (e) {
                    e.preventDefault();
                    const isShowing = payrollDropdown.classList.toggle('show');
                    this.querySelector('.chevron').classList.toggle('rotate');
                    this.setAttribute('aria-expanded', isShowing);
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function (e) {
                    if (!payrollToggle.contains(e.target) && !payrollDropdown.contains(e.target)) {
                        payrollDropdown.classList.remove('show');
                        const chevron = payrollToggle.querySelector('.chevron');
                        if (chevron) {
                            chevron.classList.remove('rotate');
                        }
                        payrollToggle.setAttribute('aria-expanded', false);
                    }
                });

                // Also close dropdown when a dropdown item is clicked
                payrollDropdown.querySelectorAll('.dropdown-item').forEach(item => {
                    item.addEventListener('click', function () {
                        payrollDropdown.classList.remove('show');
                        const chevron = payrollToggle.querySelector('.chevron');
                        if (chevron) {
                            chevron.classList.remove('rotate');
                        }
                        payrollToggle.setAttribute('aria-expanded', false);
                    });
                });
            }
        });
    </script>
</body>

</html>
<?php
// Function to format time elapsed
function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );

    foreach ($string as $k => &$v) {
        if ($diff->$k) { 
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
} 