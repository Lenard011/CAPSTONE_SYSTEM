<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Logout functionality
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie('remember_user', '', time() - 3600, "/");
    header('Location: login.php');
    exit();
}

/**
 * PHP Script: attendance.php
 * Handles attendance management including CRUD operations for employee attendance records.
 * 
 * Database: hrms_paluan
 * Table: attendance
 */

// =================================================================================
// --- Database Configuration ---
// =================================================================================

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'hrms_paluan');

// Initialize variables
$status = '';
$success_message = '';
$error_message = '';
$view_employee_id = '';
$view_attendance_records = [];
$show_employee_summary = false;
$employee_summary = [];
$current_view_params = '';

// =================================================================================
// --- PDO Database Connection ---
// =================================================================================

try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("set names utf8");
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("ERROR: Could not connect to the database. Check server logs for details.");
}


// =================================================================================
// --- Delete Attendance Record ---
// =================================================================================

if (isset($_POST['delete_attendance_id'])) {
    $attendance_id = filter_input(INPUT_POST, 'delete_attendance_id', FILTER_VALIDATE_INT);

    if ($attendance_id) {
        try {
            $get_sql = "SELECT employee_name, date, employee_id FROM attendance WHERE id = ?";
            $get_stmt = $pdo->prepare($get_sql);
            $get_stmt->execute([$attendance_id]);
            $record = $get_stmt->fetch();

            if ($record) {
                $delete_sql = "DELETE FROM attendance WHERE id = ?";
                $delete_stmt = $pdo->prepare($delete_sql);

                if ($delete_stmt->execute([$attendance_id])) {
                    $success_message = "Attendance record for " . htmlspecialchars($record['employee_name']) .
                        " on " . date('M d, Y', strtotime($record['date'])) . " deleted successfully.";

                    if (isset($_GET['view_attendance']) && $_GET['view_attendance'] == 'true' && isset($_GET['employee_id'])) {
                        $view_employee_id = $_GET['employee_id'];
                        $view_sql = "SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 100";
                        $view_stmt = $pdo->prepare($view_sql);
                        $view_stmt->execute([$view_employee_id]);
                        $view_attendance_records = $view_stmt->fetchAll();
                    }
                } else {
                    $error_message = "Error: Failed to delete attendance record.";
                }
            } else {
                $error_message = "Error: Attendance record not found.";
            }
        } catch (PDOException $e) {
            error_log("Delete attendance error: " . $e->getMessage());
            $error_message = "Database error: Failed to delete attendance record.";
        }
    } else {
        $error_message = "Error: Invalid attendance ID.";
    }
}


// =================================================================================
// --- Handle View Attendance Request with Month/Year Filters and Pagination ---
// =================================================================================

// Initialize attendance-specific variables
$attendance_total_records = 0;
$attendance_total_pages = 1;
$attendance_current_page = 1;
$attendance_all_records = 0;

if (isset($_GET['view_attendance']) && isset($_GET['employee_id'])) {
    $view_employee_id = filter_input(INPUT_GET, 'employee_id', FILTER_SANITIZE_STRING);

    // Get fresh employee details from the source tables (not from attendance)
    $employee_details = getEmployeeById($pdo, $view_employee_id);

    if ($employee_details) {
        try {
            // Pagination settings
            $records_per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
            // Validate records per page
            $valid_per_page = [10, 20, 50, 100];
            if (!in_array($records_per_page, $valid_per_page)) {
                $records_per_page = 10;
            }

            $attendance_current_page = isset($_GET['att_page']) ? (int) $_GET['att_page'] : 1;
            if ($attendance_current_page < 1)
                $attendance_current_page = 1;

            $offset = ($attendance_current_page - 1) * $records_per_page;

            // FIRST: Get total count for this specific employee (unfiltered)
            $total_count_sql = "SELECT COUNT(*) as total FROM attendance WHERE employee_id = :employee_id";
            $total_count_stmt = $pdo->prepare($total_count_sql);
            $total_count_stmt->execute([':employee_id' => $view_employee_id]);
            $attendance_all_records = $total_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // SECOND: Build the filtered count query for this specific employee
            $count_sql = "SELECT COUNT(*) as total FROM attendance WHERE employee_id = :employee_id";
            $count_params = [':employee_id' => $view_employee_id];

            // Add month filter if provided
            if (isset($_GET['month']) && !empty($_GET['month']) && is_numeric($_GET['month'])) {
                $month = (int) $_GET['month'];
                if ($month >= 1 && $month <= 12) {
                    $count_sql .= " AND MONTH(date) = :month";
                    $count_params[':month'] = $month;
                }
            }

            // Add year filter if provided
            if (isset($_GET['year']) && !empty($_GET['year']) && is_numeric($_GET['year'])) {
                $year = (int) $_GET['year'];
                $currentYear = (int) date('Y');
                if ($year >= 2000 && $year <= ($currentYear + 1)) {
                    $count_sql .= " AND YEAR(date) = :year";
                    $count_params[':year'] = $year;
                }
            }

            // Execute the count query
            $count_stmt = $pdo->prepare($count_sql);
            $count_stmt->execute($count_params);
            $attendance_total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // THIRD: Build the fetch query for this specific employee
            $view_sql = "SELECT * FROM attendance WHERE employee_id = :employee_id";
            $view_params = [':employee_id' => $view_employee_id];

            // Add month filter if provided
            if (isset($_GET['month']) && !empty($_GET['month']) && is_numeric($_GET['month'])) {
                $view_sql .= " AND MONTH(date) = :month";
                $view_params[':month'] = $_GET['month'];
            }

            // Add year filter if provided
            if (isset($_GET['year']) && !empty($_GET['year']) && is_numeric($_GET['year'])) {
                $view_sql .= " AND YEAR(date) = :year";
                $view_params[':year'] = $_GET['year'];
            }

            // Calculate total pages
            $attendance_total_pages = ($attendance_total_records > 0) ? ceil($attendance_total_records / $records_per_page) : 1;

            // Ensure current page doesn't exceed total pages
            if ($attendance_current_page > $attendance_total_pages && $attendance_total_pages > 0) {
                $attendance_current_page = $attendance_total_pages;
                $offset = ($attendance_current_page - 1) * $records_per_page;
            }

            // Add order by and pagination to the fetch query
            $view_sql .= " ORDER BY date DESC LIMIT :limit OFFSET :offset";

            $view_stmt = $pdo->prepare($view_sql);

            // Bind parameters for fetch query
            foreach ($view_params as $key => $value) {
                $view_stmt->bindValue($key, $value);
            }
            $view_stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
            $view_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $view_stmt->execute();
            $view_attendance_records = $view_stmt->fetchAll();

            // IMPORTANT: Replace the employee_name in each record with the fresh name from employee_details
            foreach ($view_attendance_records as &$record) {
                $record['employee_name'] = $employee_details['full_name'];
                $record['department'] = $employee_details['department'];
            }

            error_log("Attendance Debug - Employee: {$view_employee_id}, Records: {$attendance_total_records}, Pages: {$attendance_total_pages}");
        } catch (PDOException $e) {
            error_log("View attendance error: " . $e->getMessage());
            $error_message = "Could not retrieve attendance records for this employee.";
            $attendance_total_records = 0;
            $attendance_total_pages = 1;
            $attendance_all_records = 0;
            $view_attendance_records = [];
        }
    } else {
        $error_message = "Employee not found.";
        $attendance_total_records = 0;
        $attendance_total_pages = 1;
        $attendance_all_records = 0;
        $view_attendance_records = [];
    }
}

// =================================================================================
// --- Helper Function: Get Fresh Employee Name from Source Tables ---
// =================================================================================

function getFreshEmployeeName($pdo, $employee_id)
{
    // Check permanent
    try {
        $permanent_sql = "SELECT CONCAT(first_name, ' ', last_name) as full_name FROM permanent WHERE employee_id = ? AND status = 'Active'";
        $stmt = $pdo->prepare($permanent_sql);
        $stmt->execute([$employee_id]);
        if ($row = $stmt->fetch()) {
            return $row['full_name'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching permanent employee name: " . $e->getMessage());
    }

    // Check job order
    try {
        $joborder_sql = "SELECT employee_name as full_name FROM job_order WHERE employee_id = ? AND is_archived = 0";
        $stmt = $pdo->prepare($joborder_sql);
        $stmt->execute([$employee_id]);
        if ($row = $stmt->fetch()) {
            return $row['full_name'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching job order employee name: " . $e->getMessage());
    }

    // Check contractual
    try {
        $contractual_sql = "SELECT full_name FROM contractofservice WHERE employee_id = ? AND status = 'active'";
        $stmt = $pdo->prepare($contractual_sql);
        $stmt->execute([$employee_id]);
        if ($row = $stmt->fetch()) {
            return $row['full_name'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching contractual employee name: " . $e->getMessage());
    }

    return null;
}

// =================================================================================
// --- Helper Function: Calculate Working Hours ---
// =================================================================================

function calculateWorkingHours($am_in, $am_out, $pm_in, $pm_out)
{
    $total_minutes = 0;
    $ot_minutes = 0;
    $undertime_minutes = 0;

    $standard_start_am = strtotime('08:00:00');
    $standard_end_am = strtotime('12:00:00');
    $standard_start_pm = strtotime('13:00:00');
    $standard_end_pm = strtotime('17:00:00');

    // Process AM session
    if (!empty($am_in) && !empty($am_out)) {
        $am_in_time = strtotime($am_in);
        $am_out_time = strtotime($am_out);

        if ($am_in_time && $am_out_time && $am_out_time > $am_in_time) {
            $am_worked = ($am_out_time - $am_in_time) / 60;

            // Check for overtime (starting before 8 AM)
            if ($am_in_time < $standard_start_am) {
                $ot_minutes += ($standard_start_am - $am_in_time) / 60;
            }

            // Check for undertime (starting after 8 AM)
            if ($am_in_time > $standard_start_am) {
                $undertime_minutes += ($am_in_time - $standard_start_am) / 60;
            }

            $total_minutes += $am_worked;
        }
    }

    // Process PM session
    if (!empty($pm_in) && !empty($pm_out)) {
        $pm_in_time = strtotime($pm_in);
        $pm_out_time = strtotime($pm_out);

        if ($pm_in_time && $pm_out_time && $pm_out_time > $pm_in_time) {
            $pm_worked = ($pm_out_time - $pm_in_time) / 60;

            // Check for overtime (ending after 5 PM)
            if ($pm_out_time > $standard_end_pm) {
                $ot_minutes += ($pm_out_time - $standard_end_pm) / 60;
            }

            // Check for undertime (ending before 5 PM)
            if ($pm_out_time < $standard_end_pm) {
                $undertime_minutes += ($standard_end_pm - $pm_out_time) / 60;
            }

            $total_minutes += $pm_worked;
        }
    }

    return [
        'total_hours' => max(0, round($total_minutes / 60, 2)),
        'ot_hours' => max(0, round($ot_minutes / 60, 2)),
        'undertime_hours' => max(0, round($undertime_minutes / 60, 2))
    ];
}

// =================================================================================
// --- Get Employee Types from Database ---
// =================================================================================

function getEmployeeTypes($pdo)
{
    $employees = [];

    // Get permanent employees
    try {
        $permanent_sql = "SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name, office as department FROM permanent WHERE status = 'Active'";
        $stmt = $pdo->query($permanent_sql);
        while ($row = $stmt->fetch()) {
            $employees[] = [
                'employee_id' => $row['employee_id'],
                'full_name' => $row['full_name'],
                'department' => $row['department'],
                'type' => 'Permanent'
            ];
        }
    } catch (PDOException $e) {
        error_log("Error fetching permanent employees: " . $e->getMessage());
    }

    // Get job order employees
    try {
        $joborder_sql = "SELECT employee_id, employee_name as full_name, office as department FROM job_order WHERE is_archived = 0";
        $stmt = $pdo->query($joborder_sql);
        while ($row = $stmt->fetch()) {
            $employees[] = [
                'employee_id' => $row['employee_id'],
                'full_name' => $row['full_name'],
                'department' => $row['department'],
                'type' => 'Job Order'
            ];
        }
    } catch (PDOException $e) {
        error_log("Error fetching job order employees: " . $e->getMessage());
    }

    // Get contractual employees
    try {
        $contractual_sql = "SELECT employee_id, full_name, office as department FROM contractofservice WHERE status = 'active'";
        $stmt = $pdo->query($contractual_sql);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $employees[] = [
                'employee_id' => $row['employee_id'],
                'full_name' => $row['full_name'],
                'department' => $row['department'],
                'type' => 'Contractual'
            ];
        }
    } catch (PDOException $e) {
        error_log("Error fetching contractual employees: " . $e->getMessage());
    }

    // Sort employees by name
    usort($employees, function ($a, $b) {
        return strcmp($a['full_name'], $b['full_name']);
    });

    return $employees;
}

// =================================================================================
// --- Get Employee by ID ---
// =================================================================================

function getEmployeeById($pdo, $employee_id)
{
    // Check permanent
    try {
        $permanent_sql = "SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name, office as department FROM permanent WHERE employee_id = ? AND status = 'Active'";
        $stmt = $pdo->prepare($permanent_sql);
        $stmt->execute([$employee_id]);
        if ($row = $stmt->fetch()) {
            return [
                'employee_id' => $row['employee_id'],
                'full_name' => $row['full_name'],
                'department' => $row['department'],
                'type' => 'Permanent'
            ];
        }
    } catch (PDOException $e) {
        error_log("Error fetching permanent employee by ID: " . $e->getMessage());
    }

    // Check job order
    try {
        $joborder_sql = "SELECT employee_id, employee_name as full_name, office as department FROM job_order WHERE employee_id = ? AND is_archived = 0";
        $stmt = $pdo->prepare($joborder_sql);
        $stmt->execute([$employee_id]);
        if ($row = $stmt->fetch()) {
            return [
                'employee_id' => $row['employee_id'],
                'full_name' => $row['full_name'],
                'department' => $row['department'],
                'type' => 'Job Order'
            ];
        }
    } catch (PDOException $e) {
        error_log("Error fetching job order employee by ID: " . $e->getMessage());
    }

    // Search in contractual table
    try {
        $contractual_sql = "SELECT employee_id, full_name, office as department FROM contractofservice WHERE employee_id = ? AND status = 'active'";
        $stmt = $pdo->prepare($contractual_sql);
        $stmt->execute([$employee_id]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return [
                'employee_id' => $row['employee_id'],
                'full_name' => $row['full_name'],
                'department' => $row['department'],
                'type' => 'Contractual'
            ];
        }
    } catch (PDOException $e) {
        error_log("Error fetching contractual employee by ID: " . $e->getMessage());
    }

    return null;
}

// =================================================================================
// --- Get Employee Summary with Attendance Statistics (with Pagination & Search) ---
// =================================================================================

function getEmployeeSummary($pdo, $search = '', $department = '', $status_filter = '', $page = 1, $per_page = 10)
{
    $offset = ($page - 1) * $per_page;

    // First, get all employees from all tables
    $all_employees = [];

    // Get permanent employees
    try {
        $perm_sql = "SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name, office as department, 'Permanent' as type 
                     FROM permanent WHERE status = 'Active'";
        if (!empty($department)) {
            $perm_sql .= " AND office = :department";
        }
        $stmt = $pdo->prepare($perm_sql);
        if (!empty($department)) {
            $stmt->bindParam(':department', $department);
        }
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            $all_employees[$row['employee_id']] = $row;
        }
    } catch (PDOException $e) {
        error_log("Error in getEmployeeSummary permanent: " . $e->getMessage());
    }

    // Get job order employees
    try {
        $jo_sql = "SELECT employee_id, employee_name as full_name, office as department, 'Job Order' as type 
                   FROM job_order WHERE is_archived = 0";
        if (!empty($department)) {
            $jo_sql .= " AND office = :department";
        }
        $stmt = $pdo->prepare($jo_sql);
        if (!empty($department)) {
            $stmt->bindParam(':department', $department);
        }
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            if (!isset($all_employees[$row['employee_id']])) {
                $all_employees[$row['employee_id']] = $row;
            }
        }
    } catch (PDOException $e) {
        error_log("Error in getEmployeeSummary job order: " . $e->getMessage());
    }

    // Get contractual employees
    try {
        $cos_sql = "SELECT employee_id, full_name, office as department, 'Contractual' as type 
                    FROM contractofservice WHERE status = 'active'";
        if (!empty($department)) {
            $cos_sql .= " AND office = :department";
        }
        $stmt = $pdo->prepare($cos_sql);
        if (!empty($department)) {
            $stmt->bindParam(':department', $department);
        }
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            if (!isset($all_employees[$row['employee_id']])) {
                $all_employees[$row['employee_id']] = $row;
            }
        }
    } catch (PDOException $e) {
        error_log("Error in getEmployeeSummary contractual: " . $e->getMessage());
    }

    // Apply search filter
    if (!empty($search)) {
        $search_lower = strtolower(trim($search));
        $all_employees = array_filter($all_employees, function ($emp) use ($search_lower) {
            return (strpos(strtolower($emp['employee_id']), $search_lower) !== false) ||
                (strpos(strtolower($emp['full_name']), $search_lower) !== false) ||
                (strpos(strtolower($emp['department']), $search_lower) !== false);
        });
    }

    // Get attendance statistics for all employees
    $attendance_stats = [];
    try {
        $stats_sql = "SELECT 
                        employee_id,
                        COUNT(*) as total_records,
                        SUM(CASE WHEN total_hours > 0 THEN 1 ELSE 0 END) as present_days,
                        SUM(total_hours) as total_hours,
                        SUM(ot_hours) as total_ot,
                        SUM(under_time) as total_undertime,
                        MAX(date) as last_date
                      FROM attendance 
                      GROUP BY employee_id";
        $stmt = $pdo->query($stats_sql);
        while ($row = $stmt->fetch()) {
            $attendance_stats[$row['employee_id']] = $row;
        }
    } catch (PDOException $e) {
        error_log("Error getting attendance stats: " . $e->getMessage());
    }

    // Apply status filter
    if (!empty($status_filter)) {
        if ($status_filter == 'has_attendance') {
            $all_employees = array_filter($all_employees, function ($emp) use ($attendance_stats) {
                return isset($attendance_stats[$emp['employee_id']]) &&
                    $attendance_stats[$emp['employee_id']]['total_records'] > 0;
            });
        } elseif ($status_filter == 'no_attendance') {
            $all_employees = array_filter($all_employees, function ($emp) use ($attendance_stats) {
                return !isset($attendance_stats[$emp['employee_id']]) ||
                    $attendance_stats[$emp['employee_id']]['total_records'] == 0;
            });
        }
    }

    // Calculate total before pagination
    $total_records = count($all_employees);

    // Sort employees by name
    usort($all_employees, function ($a, $b) {
        return strcmp($a['full_name'], $b['full_name']);
    });

    // Apply pagination
    $paginated_employees = array_slice($all_employees, $offset, $per_page);

    // Build final result with attendance data
    $employees = [];
    foreach ($paginated_employees as $emp) {
        $stats = isset($attendance_stats[$emp['employee_id']]) ? $attendance_stats[$emp['employee_id']] : [
            'total_records' => 0,
            'present_days' => 0,
            'total_hours' => 0,
            'total_ot' => 0,
            'total_undertime' => 0,
            'last_date' => null
        ];

        $employees[] = [
            'employee_id' => $emp['employee_id'],
            'full_name' => $emp['full_name'],
            'department' => $emp['department'],
            'type' => $emp['type'],
            'total_records' => (int)$stats['total_records'],
            'present_days' => (int)$stats['present_days'],
            'total_hours' => floatval($stats['total_hours']),
            'total_ot' => floatval($stats['total_ot']),
            'total_undertime' => floatval($stats['total_undertime']),
            'last_date' => $stats['last_date']
        ];
    }

    return [
        'employees' => $employees,
        'total' => $total_records,
        'pages' => $total_records > 0 ? ceil($total_records / $per_page) : 1
    ];
}
// =================================================================================
// --- Generate Dates for Period ---
// =================================================================================

function generatePeriodDates($start_date, $end_date)
{
    $dates = [];
    $current_date = new DateTime($start_date);
    $end = new DateTime($end_date);

    while ($current_date <= $end) {
        $dates[] = $current_date->format('Y-m-d');
        $current_date->modify('+1 day');
    }

    return $dates;
}

// =================================================================================
// --- Handle AJAX Search Request ---
// =================================================================================

if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');

    try {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $department = isset($_GET['department']) ? trim($_GET['department']) : '';
        $status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;

        $result = getEmployeeSummary($pdo, $search, $department, $status_filter, $page, $per_page);

        // Ensure we always return valid JSON
        echo json_encode($result);
    } catch (Exception $e) {
        error_log("AJAX search error: " . $e->getMessage());
        echo json_encode([
            'employees' => [],
            'total' => 0,
            'pages' => 1
        ]);
    }
    exit();
}
// =================================================================================
// --- Handle AJAX Get All Employee IDs Request ---
// =================================================================================

if (isset($_GET['ajax_get_all_employees'])) {
    header('Content-Type: application/json');

    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $department = isset($_GET['department']) ? $_GET['department'] : '';
    $status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

    // Get all employees without pagination
    $result = getEmployeeSummary($pdo, $search, $department, $status_filter, 1, 999999);

    $employee_ids = [];
    foreach ($result['employees'] as $emp) {
        if ($emp['total_records'] > 0) {
            $employee_ids[] = [
                'id' => $emp['employee_id'],
                'name' => $emp['full_name']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'employees' => $employee_ids,
        'total' => count($employee_ids)
    ]);
    exit();
}

// =================================================================================
// --- Check if we should show employee summary view ---
// =================================================================================

// Default to employee summary view for better user experience
if (!isset($_GET['view'])) {
    $show_employee_summary = true;
} elseif (isset($_GET['view']) && $_GET['view'] == 'employees') {
    $show_employee_summary = true;
} else {
    $show_employee_summary = false;
}

// Get search and filter parameters
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$dept_filter = isset($_GET['department']) ? $_GET['department'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$records_per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;

// Get employee summary with pagination
$employee_data = getEmployeeSummary($pdo, $search_term, $dept_filter, $status_filter, $current_page, $records_per_page);
$employee_summary = $employee_data['employees'];
$total_employees = $employee_data['total'];
$total_pages = $employee_data['pages'];

// Debug - Remove in production
if (empty($employee_summary) && $total_employees > 0) {
    error_log("Employee summary empty but total employees: $total_employees");
}

// =================================================================================
// --- Bulk Add Attendance with Proper Redirect ---
// =================================================================================

if (isset($_POST['bulk_add_attendance'])) {
    $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_SANITIZE_STRING);
    $employee_name = filter_input(INPUT_POST, 'employee_name', FILTER_SANITIZE_STRING);
    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
    $start_date = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
    $end_date = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);
    $dtr_period = filter_input(INPUT_POST, 'dtr_period', FILTER_SANITIZE_STRING);

    if (empty($employee_id) || empty($employee_name) || empty($department) || empty($start_date) || empty($end_date)) {
        $error_message = "Error: Please fill all required fields.";
    } else {
        $success_count = 0;
        $error_count = 0;
        $duplicate_count = 0;
        $dates_added = [];
        $dates_skipped = [];

        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $days_count = $start->diff($end)->days + 1;

        $period_text = '';
        switch ($dtr_period) {
            case 'first_half':
                $period_text = 'First Half (Days 1-15)';
                break;
            case 'second_half':
                $period_text = 'Second Half (Days 16-30/31)';
                break;
            case 'full_month':
            default:
                $period_text = 'Full Month';
                break;
        }

        if ($days_count > 31) {
            $error_message = "Error: Maximum period is 31 days.";
        } else {
            $current_date = clone $start;

            try {
                $pdo->beginTransaction();

                for ($i = 0; $i < $days_count; $i++) {
                    $date = $current_date->format('Y-m-d');
                    $date_key = $current_date->format('Y-m-d');

                    $am_time_in = isset($_POST['am_time_in'][$date_key]) && !empty($_POST['am_time_in'][$date_key]) ? $_POST['am_time_in'][$date_key] : null;
                    $am_time_out = isset($_POST['am_time_out'][$date_key]) && !empty($_POST['am_time_out'][$date_key]) ? $_POST['am_time_out'][$date_key] : null;
                    $pm_time_in = isset($_POST['pm_time_in'][$date_key]) && !empty($_POST['pm_time_in'][$date_key]) ? $_POST['pm_time_in'][$date_key] : null;
                    $pm_time_out = isset($_POST['pm_time_out'][$date_key]) && !empty($_POST['pm_time_out'][$date_key]) ? $_POST['pm_time_out'][$date_key] : null;

                    // Skip if all times are empty
                    if (empty($am_time_in) && empty($am_time_out) && empty($pm_time_in) && empty($pm_time_out)) {
                        $current_date->modify('+1 day');
                        continue;
                    }

                    // Check for duplicate
                    $check_sql = "SELECT id FROM attendance WHERE employee_id = ? AND date = ?";
                    $check_stmt = $pdo->prepare($check_sql);
                    $check_stmt->execute([$employee_id, $date]);
                    $exists = $check_stmt->rowCount() > 0;

                    if (!$exists) {
                        $hours = calculateWorkingHours($am_time_in, $am_time_out, $pm_time_in, $pm_time_out);

                        $sql = "INSERT INTO attendance 
                                (date, employee_id, employee_name, department, am_time_in, am_time_out, pm_time_in, pm_time_out, ot_hours, under_time, total_hours) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                        $stmt = $pdo->prepare($sql);

                        if (
                            $stmt->execute([
                                $date,
                                $employee_id,
                                $employee_name,
                                $department,
                                $am_time_in,
                                $am_time_out,
                                $pm_time_in,
                                $pm_time_out,
                                $hours['ot_hours'],
                                $hours['undertime_hours'],
                                $hours['total_hours']
                            ])
                        ) {
                            $success_count++;
                            $dates_added[] = $date;
                        } else {
                            $error_count++;
                        }
                    } else {
                        $duplicate_count++;
                        $dates_skipped[] = $date;
                    }

                    $current_date->modify('+1 day');
                }

                $pdo->commit();

                // Build redirect URL with proper parameters
                $redirect_url = $_SERVER['PHP_SELF'];
                $query_params = [];

                if (isset($_GET['view_attendance']) && $_GET['view_attendance'] == 'true' && isset($_GET['employee_id'])) {
                    $query_params['view_attendance'] = 'true';
                    $query_params['employee_id'] = $_GET['employee_id'];
                } else {
                    // Default to employee summary view
                    $query_params['view'] = 'employees';
                }

                if ($success_count > 0) {
                    $query_params['status'] = 'bulk_add_success';
                    $query_params['success_count'] = $success_count;
                    $query_params['employee_name'] = $employee_name;
                    $query_params['period'] = $period_text;
                    $query_params['start_date'] = $start_date;
                    $query_params['end_date'] = $end_date;
                    $query_params['duplicate_count'] = $duplicate_count;
                } else if ($duplicate_count > 0) {
                    $query_params['status'] = 'bulk_add_all_duplicates';
                    $query_params['duplicate_count'] = $duplicate_count;
                    $query_params['employee_name'] = $employee_name;
                    $query_params['period'] = $period_text;
                } else {
                    $query_params['status'] = 'bulk_add_no_records';
                }

                if (!empty($query_params)) {
                    $redirect_url .= '?' . http_build_query($query_params);
                }

                header("Location: " . $redirect_url);
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Bulk add attendance error: " . $e->getMessage());
                $error_message = "Database error: Failed to add attendance records. Please try again.";
            }
        }
    }
}

// =================================================================================
// --- Generate CSV Template ---
// =================================================================================

if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_template_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, [
        'date',
        'employee_id',
        'employee_name',
        'department',
        'am_time_in',
        'am_time_out',
        'pm_time_in',
        'pm_time_out'
    ]);

    $sampleData = [
        [date('Y-m-d'), '20230606', 'Jorel Vicente', 'Office of the Municipal Mayor', '08:00', '12:00', '13:00', '17:00'],
        [date('Y-m-d'), '20170101', 'Maylin Cajayon', 'Office of the Municipal Mayor', '08:15', '12:05', '13:10', '17:30'],
    ];

    foreach ($sampleData as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit();
}

// =================================================================================
// --- Get Search and Filter Parameters for Employee Summary ---
// =================================================================================

// Default to employee summary view for better user experience
if (!isset($_GET['view'])) {
    $show_employee_summary = true;
} elseif (isset($_GET['view']) && $_GET['view'] == 'employees') {
    $show_employee_summary = true;
} else {
    $show_employee_summary = false;
}

// Get search and filter parameters
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$dept_filter = isset($_GET['department']) ? $_GET['department'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$records_per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;

// Get employee summary with pagination
$employee_data = getEmployeeSummary($pdo, $search_term, $dept_filter, $status_filter, $current_page, $records_per_page);
$employee_summary = $employee_data['employees'];
$total_employees = $employee_data['total'];
$total_pages = $employee_data['pages'];

// =================================================================================
// --- Get All Employees for Auto-complete ---
// =================================================================================

$all_employees = getEmployeeTypes($pdo);

$current_view_params = '';
if (isset($_GET['view_attendance']) && $_GET['view_attendance'] == 'true' && isset($_GET['employee_id'])) {
    $current_view_params = '?view_attendance=true&employee_id=' . urlencode($_GET['employee_id']);
}

// Handle status messages from GET parameters
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'edit_success':
            $success_message = "Attendance record updated successfully!";
            break;
        case 'delete_success':
            $success_message = "Attendance record deleted successfully!";
            break;
        case 'bulk_add_success':
            $success_count = isset($_GET['success_count']) ? intval($_GET['success_count']) : 0;
            $employee_name = isset($_GET['employee_name']) ? $_GET['employee_name'] : '';
            $period = isset($_GET['period']) ? $_GET['period'] : '';
            $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
            $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
            $duplicate_count = isset($_GET['duplicate_count']) ? intval($_GET['duplicate_count']) : 0;

            $success_message = "Successfully added $success_count attendance records for $employee_name for $period ($start_date to $end_date).";
            if ($duplicate_count > 0) {
                $success_message .= " $duplicate_count records were skipped (already exist).";
            }
            break;
        case 'bulk_add_all_duplicates':
            $duplicate_count = isset($_GET['duplicate_count']) ? intval($_GET['duplicate_count']) : 0;
            $employee_name = isset($_GET['employee_name']) ? $_GET['employee_name'] : '';
            $period = isset($_GET['period']) ? $_GET['period'] : '';
            $error_message = "No new records were added. All $duplicate_count records for $employee_name for $period already exist in the database.";
            break;
        case 'bulk_add_no_records':
            $error_message = "No records were added. Please check if you entered any time entries for work days.";
            break;
        case 'edit_error':
            $error_message = isset($_GET['message']) ? $_GET['message'] : "Error updating attendance record.";
            break;
    }
}

// Departments list for dropdowns
$departments = [
    'Office of the Municipal Mayor',
    'Human Resource Management Division',
    'Business Permit and Licensing Division',
    'Sangguniang Bayan Office',
    'Office of the Municipal Accountant',
    'Office of the Assessor',
    'Municipal Budget Office',
    'Municipal Planning and Development Office',
    'Municipal Engineering Office',
    'Municipal Disaster Risk Reduction and Management Office',
    'Municipal Social Welfare and Development Office',
    'Municipal Environment and Natural Resources Office',
    'Office of the Municipal Agriculturist',
    'Municipal General Services Office',
    'Municipal Public Employment Service Office',
    'Municipal Health Office',
    'Municipal Treasurer\'s Office'
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management - HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.1.1/flowbite.min.css" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* EXACT STYLES FROM Employee.php */
        :root {
            --primary: #1e40af;
            --primary-dark: #1e3a8a;
            --primary-light: #3b82f6;
            --secondary: #6366f1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --dark: #1f2937;
            --light: #f8fafc;
            --gradient-primary: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: var(--dark);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Navbar Styling - EXACT from Employee.php */
        .navbar {
            background: var(--gradient-primary);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 70px;
            z-index: 50;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
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
            gap: 1rem;
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
            transition: all 0.3s ease;
            color: white;
        }

        .mobile-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
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

        /* Toast Notifications - EXACT from Employee.php */
        .toast-container {
            position: fixed;
            top: 90px;
            right: 10px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-width: 95%;
        }

        .toast {
            background: white;
            border-radius: 10px;
            padding: 0.9rem 1rem;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            min-width: 280px;
            max-width: 400px;
            animation: slideInRight 0.3s ease;
            transform: translateX(100%);
            opacity: 0;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .toast.success {
            border-left-color: #10b981;
        }

        .toast.error {
            border-left-color: #ef4444;
        }

        .toast-icon {
            font-size: 1.1rem;
        }

        .toast.success .toast-icon {
            color: #10b981;
        }

        .toast.error .toast-icon {
            color: #ef4444;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 0.2rem;
            font-size: 0.9rem;
        }

        .toast-message {
            font-size: 0.8rem;
            color: #6b7280;
        }

        .toast-close {
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 0.9rem;
            padding: 0.2rem;
        }

        /* Sidebar - EXACT from Employee.php */
        .sidebar-container {
            position: fixed;
            left: 0;
            top: 70px;
            width: 260px;
            height: calc(100vh - 70px);
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 100%);
            z-index: 50;
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
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 8px;
        }

        .dropdown-menu .dropdown-item:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .dropdown-menu .dropdown-item i {
            font-size: 0.75rem;
            margin-right: 0.5rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .chevron {
            transition: transform 0.3s ease;
        }

        .chevron.rotate {
            transform: rotate(180deg);
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.75rem;
        }

        /* Overlay for mobile - EXACT from Employee.php */
        .sidebar-overlay {
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

        .sidebar-overlay.active {
            display: block;
        }

        /* Main Content - EXACT from Employee.php */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 1.5rem;
            min-height: calc(100vh - 70px);
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Breadcrumb - EXACT from Employee.php */
        .breadcrumb {
            background: #f8fafc;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
        }

        .breadcrumb ol {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .breadcrumb li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: #3b82f6;
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumb a:hover {
            color: #1e40af;
            text-decoration: underline;
        }

        /* Dashboard Grid - EXACT from Employee.php */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-light), var(--primary));
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }

        .stat-title {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: #1f2937;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        /* Search Bar - EXACT from Employee.php */
        .search-container {
            background: white;
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
            margin-bottom: 2rem;
        }

        .search-wrapper {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .search-input {
            flex: 1;
            position: relative;
        }

        .search-input input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .search-input input:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-input i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .search-button {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            border: none;
            padding: 0.875rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .search-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        /* Responsive Design - EXACT from Employee.php */
        @media (max-width: 1200px) {
            .sidebar-container {
                transform: translateX(-100%);
            }

            .sidebar-container.active {
                transform: translateX(0);
            }

            .mobile-toggle {
                display: flex;
            }

            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .datetime-container {
                display: none;
            }

            .brand-text {
                display: none;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .search-wrapper {
                flex-direction: column;
            }

            .search-button {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .navbar-container {
                padding: 0 0.75rem;
            }

            .main-content {
                padding: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-value {
                font-size: 1.6rem;
            }
        }

        /* Scrollbar Styling - EXACT from Employee.php */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }

        /* Modal Styles - EXACT from Employee.php */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1100;
            padding: 1rem;
        }

        .modal-overlay.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: white;
            border-radius: 18px;
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            padding: 1.25rem 1.5rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .close-button {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
        }

        .close-button:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-subheader {
            padding: 1rem 1.5rem;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-subheader p {
            color: #4b5563;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .modal-body {
            padding: 1.25rem;
            overflow-y: auto;
            max-height: 50vh;
        }

        .loading-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: #6b7280;
        }

        .spinner {
            width: 2.5rem;
            height: 2.5rem;
            border: 3px solid #e5e7eb;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .employee-table {
            width: 100%;
            border-collapse: collapse;
        }

        .employee-table th {
            padding: 0.75rem 0.5rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e5e7eb;
        }

        .employee-table td {
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid #e5e7eb;
            color: #4b5563;
            word-break: break-word;
        }

        .employee-table tr:last-child td {
            border-bottom: none;
        }

        .employee-name {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.9rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
            white-space: nowrap;
        }

        /* Status badge colors */
        .bg-indigo-100 {
            background-color: #e0e7ff;
        }

        .text-indigo-800 {
            color: #3730a3;
        }

        .bg-amber-100 {
            background-color: #fef3c7;
        }

        .text-amber-800 {
            color: #92400e;
        }

        .bg-red-100 {
            background-color: #fee2e2;
        }

        .text-red-800 {
            color: #991b1b;
        }

        /* Utility Classes */
        .hidden {
            display: none !important;
        }

        .flex {
            display: flex;
        }

        .items-center {
            align-items: center;
        }

        .justify-center {
            justify-content: center;
        }

        .text-center {
            text-align: center;
        }

        /* Your existing attendance-specific styles (keep all of them) */
        .table-container::-webkit-scrollbar {
            height: 6px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        .attendance-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-present {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-absent {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-late {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-leave {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .action-btn {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            transition: all 0.2s ease;
        }

        .view-btn {
            background-color: #3b82f6;
            color: white;
            border: 1px solid #2563eb;
        }

        .view-btn:hover {
            background-color: #2563eb;
        }

        .edit-btn {
            background-color: #10b981;
            color: white;
            border: 1px solid #059669;
        }

        .edit-btn:hover {
            background-color: #059669;
        }

        .delete-btn {
            background-color: #ef4444;
            color: white;
            border: 1px solid #dc2626;
        }

        .delete-btn:hover {
            background-color: #dc2626;
        }

        .filter-badge {
            background-color: #3b82f6;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .filter-badge .remove-filter {
            cursor: pointer;
            margin-left: 5px;
            font-size: 0.7rem;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 16px;
            height: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .filter-badge .remove-filter:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .employee-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 15px;
        }

        .pagination-container {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
        }

        @media (min-width: 640px) {
            .pagination-container {
                flex-direction: row;
            }
        }

        .pagination-info {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .pagination-nav {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.5rem;
            height: 2.5rem;
            padding: 0 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #4b5563;
            background-color: white;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
        }

        .pagination-btn:hover:not(:disabled):not(.active) {
            background-color: #f3f4f6;
            border-color: #9ca3af;
            color: #374151;
        }

        .pagination-btn.active {
            background-color: #3b82f6;
            border-color: #3b82f6;
            color: white;
        }

        .time-input {
            width: 100px;
            text-align: center;
        }

        .bulk-table-container {
            max-height: 400px;
            overflow-y: auto;
        }

        .weekend-row {
            background-color: #fef3c7;
        }

        .holiday-row {
            background-color: #fee2e2;
        }

        .future-date {
            opacity: 0.5;
            pointer-events: none;
        }

        .future-date input {
            background-color: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
        }

        .global-select-badge {
            background-color: #10b981;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .global-select-badge:hover {
            background-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
        }

        .selection-info {
            background-color: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .clear-selection-btn {
            color: #ef4444;
            background-color: #fee2e2;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid #fecaca;
        }

        .clear-selection-btn:hover {
            background-color: #fecaca;
            color: #b91c1c;
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Navigation Header (Keep exactly as original) -->
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

    <!-- Mobile Overlay - EXACT from Employee.php -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <!-- Sidebar - EXACT from Employee.php -->
    <div class="sidebar-container" id="sidebar-container">
        <div class="sidebar">
            <div class="sidebar-content">
                <!-- Dashboard -->
                <a href="dashboard.php" class="sidebar-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard Analytics</span>
                </a>

                <!-- Employees -->
                <a href="./employees/Employee.php" class="sidebar-item">
                    <i class="fas fa-users"></i>
                    <span>Employees</span>
                </a>

                <!-- Attendance -->
                <a href="#" class="sidebar-item active">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>

                <!-- Payroll with dropdown - EXACT from Employee.php -->
                <a href="#" class="sidebar-item" id="payroll-toggle">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payroll</span>
                    <i class="fas fa-chevron-down chevron ml-auto"></i>
                </a>
                <div class="dropdown-menu" id="payroll-dropdown">
                    <a href="Payrollmanagement/contractualpayrolltable1.php" class="dropdown-item">
                        <i class="fas fa-circle text-xs" style="font-size: 8px; color: rgba(255,255,255,0.6);"></i>
                        Contractual
                    </a>
                    <a href="Payrollmanagement/joboerderpayrolltable1.php" class="dropdown-item">
                        <i class="fas fa-circle text-xs" style="font-size: 8px; color: rgba(255,255,255,0.6);"></i>
                        Job Order
                    </a>
                    <a href="Payrollmanagement/permanentpayrolltable1.php" class="dropdown-item">
                        <i class="fas fa-circle text-xs" style="font-size: 8px; color: rgba(255,255,255,0.6);"></i>
                        Permanent
                    </a>
                </div>

                <!-- Settings -->
                <a href="../settings.php" class="sidebar-item">
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
                <div class="text-center text-white/60 text-sm">
                    <p>HRMS v2.0</p>
                    <p class="text-xs mt-1">© 2024 Paluan LGU</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Check if we're viewing a specific employee's attendance -->
        <?php if (isset($_GET['view_attendance']) && $view_employee_id && isset($employee_details)): ?>
            <!-- View Employee Attendance Section -->
            <div class="p-4 md:p-6 bg-white rounded-lg shadow-md">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900">Attendance Records</h1>
                        <p class="text-gray-600">for <?php echo htmlspecialchars($employee_details['full_name']); ?></p>
                    </div>
                    <div class="flex gap-2">
                        <a href="?view=employees"
                            class="text-white bg-blue-600 hover:bg-blue-700 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to Employee Summary
                        </a>
                    </div>
                </div>

                <!-- Status Messages -->
                <?php if (!empty($success_message)): ?>
                    <div class="p-3 md:p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 import-success" role="alert">
                        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="p-3 md:p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 import-error" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Month/Year Filter Section -->
                <div class="bg-gray-50 p-4 rounded-lg mb-4 card-shadow">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Attendance Records</h3>
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-filter text-gray-500"></i>
                            <span class="font-medium text-gray-700">Filter by:</span>
                        </div>

                        <div class="flex flex-wrap gap-3 items-center">
                            <!-- Month Filter -->
                            <div class="relative">
                                <select id="monthFilter"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-40 p-2.5">
                                    <option value="">All Months</option>
                                    <?php
                                    $months = [
                                        1 => 'January',
                                        2 => 'February',
                                        3 => 'March',
                                        4 => 'April',
                                        5 => 'May',
                                        6 => 'June',
                                        7 => 'July',
                                        8 => 'August',
                                        9 => 'September',
                                        10 => 'October',
                                        11 => 'November',
                                        12 => 'December'
                                    ];
                                    $selectedMonth = isset($_GET['month']) ? (int) $_GET['month'] : '';
                                    foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" <?php echo $selectedMonth == $num ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Year Filter -->
                            <div class="relative">
                                <select id="yearFilter"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-28 p-2.5">
                                    <option value="">All Years</option>
                                    <?php
                                    $currentYear = (int) date('Y');
                                    $selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : '';
                                    for ($year = $currentYear; $year >= $currentYear - 5; $year--): ?>
                                        <option value="<?php echo $year; ?>" <?php echo $selectedYear == $year ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <!-- Filter Action Buttons -->
                            <button type="button" onclick="applyAttendanceFilter()"
                                class="text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out flex items-center">
                                <i class="fas fa-search mr-2"></i>
                                Apply Filter
                            </button>

                            <button type="button" onclick="clearAttendanceFilter()"
                                class="text-gray-700 bg-gray-200 hover:bg-gray-300 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out flex items-center">
                                <i class="fas fa-times mr-2"></i>
                                Clear
                            </button>
                        </div>
                    </div>

                    <!-- Active Filter Display -->
                    <?php if (isset($_GET['month']) || isset($_GET['year'])): ?>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="text-sm text-gray-600 mr-2">Active filters:</span>
                            <?php if (isset($_GET['month']) && !empty($_GET['month'])): ?>
                                <span class="filter-badge">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo $months[(int) $_GET['month']] ?? ''; ?>
                                    <span class="remove-filter" onclick="clearAttendanceFilter()">×</span>
                                </span>
                            <?php endif; ?>
                            <?php if (isset($_GET['year']) && !empty($_GET['year'])): ?>
                                <span class="filter-badge">
                                    <i class="fas fa-calendar-alt"></i>
                                    Year <?php echo htmlspecialchars($_GET['year']); ?>
                                    <span class="remove-filter" onclick="clearAttendanceFilter()">×</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Employee Summary -->
                <div class="employee-summary">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                        <div>
                            <h2 class="text-xl font-bold mb-1">
                                <?php echo htmlspecialchars($employee_details['full_name']); ?>
                            </h2>
                            <p class="text-white/80">ID: <?php echo htmlspecialchars($employee_details['employee_id']); ?> |
                                <?php echo htmlspecialchars($employee_details['type']); ?> Employee
                            </p>
                        </div>
                        <div class="mt-4 md:mt-0 text-right">
                            <p class="text-white/80">Department:
                                <?php echo htmlspecialchars($employee_details['department']); ?>
                            </p>
                            <p class="text-white/80">Total Records: <?php echo $attendance_all_records; ?></p>
                            <?php if (isset($_GET['month']) || isset($_GET['year'])): ?>
                                <p class="text-white/80 text-sm">Showing: <?php echo $attendance_total_records; ?> filtered
                                    records</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Attendance Statistics - Using FILTERED records -->
                <?php if (!empty($view_attendance_records)): ?>
                    <?php
                    // Calculate statistics based on FILTERED records only
                    $present_count = 0;
                    $absent_count = 0;
                    $late_count = 0;
                    $total_hours = 0;
                    $total_ot = 0;

                    foreach ($view_attendance_records as $record) {
                        if ($record['total_hours'] > 0)
                            $present_count++;
                        if ($record['total_hours'] == 0)
                            $absent_count++;
                        if ($record['under_time'] > 0)
                            $late_count++;
                        $total_hours += $record['total_hours'];
                        $total_ot += $record['ot_hours'];
                    }

                    $present_rate = count($view_attendance_records) > 0 ? round(($present_count / count($view_attendance_records)) * 100, 1) : 0;
                    ?>

                    <!-- Filter Info Banner -->
                    <?php if (isset($_GET['month']) || isset($_GET['year'])): ?>
                        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-4" role="alert">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle mr-2"></i>
                                <p>
                                    <span class="font-bold">Filtered View:</span>
                                    Showing statistics for
                                    <?php
                                    $filter_parts = [];
                                    if (isset($_GET['month'])) {
                                        $filter_parts[] = $months[(int) $_GET['month']];
                                    }
                                    if (isset($_GET['year'])) {
                                        $filter_parts[] = $_GET['year'];
                                    }
                                    echo implode(' ', $filter_parts);
                                    ?> only.
                                    <a href="?view_attendance=true&employee_id=<?php echo urlencode($view_employee_id); ?>"
                                        class="underline ml-2 font-semibold">
                                        Clear filter to see all records
                                    </a>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="stats-card">
                            <div class="flex items-center">
                                <div class="p-3 rounded-lg bg-green-100 mr-4">
                                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Present Days</p>
                                    <p class="text-2xl font-bold"><?php echo $present_count; ?></p>
                                    <p class="text-xs text-green-600"><?php echo $present_rate; ?>% Present Rate</p>
                                </div>
                            </div>
                        </div>

                        <div class="stats-card">
                            <div class="flex items-center">
                                <div class="p-3 rounded-lg bg-red-100 mr-4">
                                    <i class="fas fa-times-circle text-red-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Absent Days</p>
                                    <p class="text-2xl font-bold"><?php echo $absent_count; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="stats-card">
                            <div class="flex items-center">
                                <div class="p-3 rounded-lg bg-yellow-100 mr-4">
                                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Late Days</p>
                                    <p class="text-2xl font-bold"><?php echo $late_count; ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="stats-card">
                            <div class="flex items-center">
                                <div class="p-3 rounded-lg bg-blue-100 mr-4">
                                    <i class="fas fa-chart-line text-blue-600 text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Total Hours</p>
                                    <p class="text-2xl font-bold"><?php echo round($total_hours, 1); ?></p>
                                    <p class="text-xs text-blue-600"><?php echo round($total_ot, 1); ?>h OT</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search Results Info (showing filtered vs total) -->
                    <div class="mb-4 flex justify-between items-center">
                        <div class="text-sm text-gray-600">
                            <?php if (isset($_GET['month']) || isset($_GET['year'])): ?>
                                <span class="font-semibold text-blue-600">Filtered:</span>
                            <?php endif; ?>

                            Showing
                            <?php if (isset($attendance_total_records) && $attendance_total_records > 0): ?>
                                <span class="font-semibold"><?php echo $offset + 1; ?></span> to
                                <span
                                    class="font-semibold"><?php echo min($offset + $records_per_page, $attendance_total_records); ?></span>
                                of
                                <span class="font-semibold"><?php echo $attendance_total_records; ?></span> records
                            <?php else: ?>
                                <span class="font-semibold">0</span> records
                            <?php endif; ?>

                            <?php if (isset($_GET['month']) || isset($_GET['year'])): ?>
                                <span class="ml-2 text-blue-600">(filtered from <?php echo $attendance_all_records; ?> total
                                    records)</span>
                            <?php endif; ?>
                        </div>

                        <!-- Records per page selector -->
                        <?php if (isset($attendance_total_pages) && $attendance_total_pages > 1): ?>
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-gray-600">Show:</label>
                                <select id="attPerPage" onchange="changeAttendancePerPage(this.value)"
                                    class="border border-gray-300 rounded-lg text-sm p-1.5">
                                    <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="20" <?php echo $records_per_page == 20 ? 'selected' : ''; ?>>20</option>
                                    <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Attendance Table -->
                    <div class="table-container overflow-x-auto rounded-lg border border-gray-200 mobile-table-container">
                        <table class="w-full text-sm text-left text-gray-900 mobile-table">
                            <thead class="text-xs text-white uppercase bg-blue-600">
                                <tr>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm">Date</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm">Day</th>
                                    <th scope="col" class="px-4 py-3 text-center mobile-text-sm" colspan="2">AM</th>
                                    <th scope="col" class="px-4 py-3 text-center mobile-text-sm" colspan="2">PM</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm mobile-hidden">OT</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm mobile-hidden">UnderTime</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm mobile-hidden">Total</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm">Status</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($view_attendance_records as $row):
                                    $day_name = date('D', strtotime($row['date']));
                                    $is_weekend = ($day_name == 'Sat' || $day_name == 'Sun');

                                    // Determine status
                                    $status = 'Present';
                                    $status_class = 'status-present';

                                    if ($row['total_hours'] == 0) {
                                        $status = 'Absent';
                                        $status_class = 'status-absent';
                                    } elseif ($row['under_time'] > 0) {
                                        $status = 'Late';
                                        $status_class = 'status-late';
                                    } elseif ($is_weekend && $row['total_hours'] > 0) {
                                        $status = 'Weekend Work';
                                        $status_class = 'status-leave';
                                    }
                                ?>
                                    <tr class="bg-white hover:bg-gray-50 transition-colors duration-150">
                                        <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap mobile-text-sm">
                                            <?php echo date('M d, Y', strtotime($row['date'])); ?>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 mobile-text-sm"><?php echo $day_name; ?></td>
                                        <td class="px-4 py-3 text-center text-gray-700 mobile-text-sm">
                                            <?php echo !empty($row['am_time_in']) ? date('h:i A', strtotime($row['am_time_in'])) : '<span class="text-gray-400">--</span>'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center text-gray-700 mobile-text-sm">
                                            <?php echo !empty($row['am_time_out']) ? date('h:i A', strtotime($row['am_time_out'])) : '<span class="text-gray-400">--</span>'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center text-gray-700 mobile-text-sm">
                                            <?php echo !empty($row['pm_time_in']) ? date('h:i A', strtotime($row['pm_time_in'])) : '<span class="text-gray-400">--</span>'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center text-gray-700 mobile-text-sm">
                                            <?php echo !empty($row['pm_time_out']) ? date('h:i A', strtotime($row['pm_time_out'])) : '<span class="text-gray-400">--</span>'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 mobile-text-sm mobile-hidden">
                                            <?php echo $row['ot_hours']; ?>h
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 mobile-text-sm mobile-hidden">
                                            <?php echo $row['under_time']; ?>h
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-gray-900 mobile-text-sm mobile-hidden">
                                            <?php echo $row['total_hours']; ?>h
                                        </td>
                                        <td class="px-4 py-3 mobile-text-sm">
                                            <span class="attendance-status <?php echo $status_class; ?>">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 mobile-text-sm text-center">
                                            <div class="flex space-x-1 justify-center">
                                                <button type="button" onclick="editAttendance(<?php echo $row['id']; ?>)"
                                                    class="action-btn edit-btn" title="Edit Record">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button"
                                                    onclick="deleteAttendance(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($employee_details['full_name']); ?>', '<?php echo $row['date']; ?>')"
                                                    class="action-btn delete-btn" title="Delete Record">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <!-- Attendance Table -->
                        <?php if (!empty($view_attendance_records)): ?>
                            <!-- ... existing table code ... -->
                        <?php else: ?>
                            <div class="text-center py-12">
                                <!-- ... existing no records message ... -->
                            </div>
                        <?php endif; ?>

                        <!-- Pagination - Show ALWAYS when there are pages, even if current page has no records -->
                        <?php if (isset($attendance_total_pages) && $attendance_total_pages > 0): ?>
                            <div id="attendancePaginationContainer" class="pagination-container mt-4">
                                <div class="pagination-info">
                                    Page <span id="attCurrentPage"><?php echo $attendance_current_page; ?></span> of
                                    <span id="attTotalPages"><?php echo $attendance_total_pages; ?></span>
                                    <?php if ($attendance_total_records > 0): ?>
                                        (<?php echo $attendance_total_records; ?> records)
                                    <?php else: ?>
                                        (No records found)
                                    <?php endif; ?>
                                </div>

                                <?php if ($attendance_total_pages > 1): ?>
                                    <div class="pagination-nav" id="attendancePaginationNav">
                                        <?php
                                        // First page button
                                        if ($attendance_current_page > 1) {
                                            echo '<button onclick="changeAttendancePage(1)" class="pagination-btn" title="First Page">
                            <i class="fas fa-angle-double-left"></i>
                          </button>';
                                        } else {
                                            echo '<button disabled class="pagination-btn" title="First Page">
                            <i class="fas fa-angle-double-left"></i>
                          </button>';
                                        }

                                        // Previous page button
                                        if ($attendance_current_page > 1) {
                                            echo '<button onclick="changeAttendancePage(' . ($attendance_current_page - 1) . ')" class="pagination-btn" title="Previous Page">
                            <i class="fas fa-angle-left"></i>
                          </button>';
                                        } else {
                                            echo '<button disabled class="pagination-btn" title="Previous Page">
                            <i class="fas fa-angle-left"></i>
                          </button>';
                                        }

                                        // Calculate range of page numbers to show
                                        $start_page = max(1, $attendance_current_page - 2);
                                        $end_page = min($attendance_total_pages, $attendance_current_page + 2);

                                        // Show first page with ellipsis if needed
                                        if ($start_page > 1) {
                                            echo '<button onclick="changeAttendancePage(1)" class="pagination-btn">1</button>';
                                            if ($start_page > 2) {
                                                echo '<span class="pagination-ellipsis">...</span>';
                                            }
                                        }

                                        // Show page numbers in range
                                        for ($i = $start_page; $i <= $end_page; $i++) {
                                            $active_class = ($i == $attendance_current_page) ? 'active' : '';
                                            echo "<button onclick=\"changeAttendancePage($i)\" class=\"pagination-btn $active_class\">$i</button>";
                                        }

                                        // Show last page with ellipsis if needed
                                        if ($end_page < $attendance_total_pages) {
                                            if ($end_page < $attendance_total_pages - 1) {
                                                echo '<span class="pagination-ellipsis">...</span>';
                                            }
                                            echo "<button onclick=\"changeAttendancePage($attendance_total_pages)\" class=\"pagination-btn\">$attendance_total_pages</button>";
                                        }

                                        // Next page button
                                        if ($attendance_current_page < $attendance_total_pages) {
                                            echo '<button onclick="changeAttendancePage(' . ($attendance_current_page + 1) . ')" class="pagination-btn" title="Next Page">
                            <i class="fas fa-angle-right"></i>
                          </button>';
                                        } else {
                                            echo '<button disabled class="pagination-btn" title="Next Page">
                            <i class="fas fa-angle-right"></i>
                          </button>';
                                        }

                                        // Last page button
                                        if ($attendance_current_page < $attendance_total_pages) {
                                            echo '<button onclick="changeAttendancePage(' . $attendance_total_pages . ')" class="pagination-btn" title="Last Page">
                            <i class="fas fa-angle-double-right"></i>
                          </button>';
                                        } else {
                                            echo '<button disabled class="pagination-btn" title="Last Page">
                            <i class="fas fa-angle-double-right"></i>
                          </button>';
                                        }
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-times text-5xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">No Attendance Records Found</h3>
                        <p class="text-gray-500">
                            <?php if (isset($_GET['month']) || isset($_GET['year'])): ?>
                                No records found for the selected filter.
                                <a href="?view_attendance=true&employee_id=<?php echo urlencode($view_employee_id); ?>"
                                    class="text-blue-600 underline">
                                    Clear filter to see all records
                                </a>
                            <?php else: ?>
                                This employee doesn't have any attendance records yet.
                            <?php endif; ?>
                        </p>
                        <div class="mt-4">
                            <p class="text-sm text-gray-600">Please use the Monthly DTR Entry to add attendance records.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Employee Summary View (Default) with Global Search -->
            <div class="p-4 md:p-6 bg-white rounded-lg shadow-md">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900">Employee Attendance Summary</h1>
                        <p class="text-gray-600">View and manage attendance records for all employees</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" data-modal-target="bulkAddAttendanceModal"
                            data-modal-toggle="bulkAddAttendanceModal"
                            class="text-white bg-yellow-600 hover:bg-yellow-700 focus:ring-4 focus:ring-yellow-300 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out mobile-full flex items-center justify-center">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            <span>Monthly DTR Entry</span>
                        </button>
                        <button type="button" data-modal-target="importAttendanceModal"
                            data-modal-toggle="importAttendanceModal"
                            class="text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:ring-purple-300 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out mobile-full flex items-center justify-center">
                            <i class="fas fa-file-import mr-2"></i>
                            <span>Import</span>
                        </button>
                        <button type="button" id="exportBtn"
                            class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out mobile-full flex items-center justify-center">
                            <i class="fas fa-download mr-2"></i>
                            <span>Export</span>
                        </button>
                    </div>
                </div>

                <!-- Global Selection Info -->
                <div id="globalSelectionInfo" class="selection-info hidden">
                    <div class="selection-info-text">
                        <i class="fas fa-check-circle text-blue-600 text-xl"></i>
                        <span>
                            <span id="globalSelectedCount">0</span> employee(s) selected across all pages
                            <span id="globalSelectedList" class="text-sm text-gray-600 ml-2 hidden">
                                (<span id="globalSelectedNames"></span>)
                            </span>
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <span id="clearGlobalSelection" class="clear-selection-btn">
                            <i class="fas fa-times mr-1"></i> Clear Selection
                        </span>
                    </div>
                </div>

                <!-- Status Messages -->
                <?php if (!empty($success_message)): ?>
                    <div class="p-3 md:p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 import-success" role="alert">
                        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="p-3 md:p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 import-error" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Global Search and Filters -->
                <div class="bg-gray-50 p-4 rounded-lg mb-6 card-shadow">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Global Search & Filters</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="md:col-span-2">
                            <label for="global_search" class="block text-sm font-medium text-gray-700 mb-1">Search
                                Employees:</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-search text-gray-400 text-sm"></i>
                                </div>
                                <input type="search" id="global_search"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5"
                                    placeholder="Search by name, ID, or department... (across all pages)"
                                    value="<?php echo htmlspecialchars($search_term); ?>">
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                Searches across all <?php echo number_format($total_employees); ?> employees
                            </p>
                        </div>

                        <div>
                            <label for="global_department"
                                class="block text-sm font-medium text-gray-700 mb-1">Department:</label>
                            <select id="global_department"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $dept_filter == $dept ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="global_status" class="block text-sm font-medium text-gray-700 mb-1">Attendance
                                Status:</label>
                            <select id="global_status"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="">All Employees</option>
                                <option value="has_attendance" <?php echo $status_filter == 'has_attendance' ? 'selected' : ''; ?>>Has Attendance Records</option>
                                <option value="no_attendance" <?php echo $status_filter == 'no_attendance' ? 'selected' : ''; ?>>No Attendance Records</option>
                            </select>
                        </div>
                    </div>

                    <!-- Global Select All Button -->
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="global-select-badge" id="globalSelectAllBtn">
                            <i class="fas fa-check-double"></i>
                            Select All Employees With Records (<?php echo $total_employees; ?> total)
                        </span>
                        <span class="text-xs text-gray-500">
                            <i class="fas fa-info-circle"></i> This will select all employees who have attendance records
                            across all pages
                        </span>
                    </div>

                    <!-- Active Filters -->
                    <div id="activeFilters" class="mt-3 flex flex-wrap gap-2">
                        <?php if (!empty($search_term)): ?>
                            <span class="filter-badge">
                                <i class="fas fa-search"></i> "<?php echo htmlspecialchars($search_term); ?>"
                                <span class="remove-filter" onclick="clearSearch()">×</span>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($dept_filter)): ?>
                            <span class="filter-badge">
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($dept_filter); ?>
                                <span class="remove-filter" onclick="clearDepartment()">×</span>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($status_filter)): ?>
                            <span class="filter-badge">
                                <i class="fas fa-filter"></i>
                                <?php echo $status_filter == 'has_attendance' ? 'Has Records' : 'No Records'; ?>
                                <span class="remove-filter" onclick="clearStatus()">×</span>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($search_term) || !empty($dept_filter) || !empty($status_filter)): ?>
                            <button onclick="clearAllFilters()" class="text-xs text-red-600 hover:text-red-800">
                                <i class="fas fa-times-circle mr-1"></i>Clear All Filters
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Search Results Info -->
                <div id="searchResultsInfo" class="mb-4 flex justify-between items-center">
                    <div class="text-sm text-gray-600">
                        Showing <span
                            id="showingFrom"><?php echo min(1, (($current_page - 1) * $records_per_page) + 1); ?></span> to
                        <span id="showingTo"><?php echo min($current_page * $records_per_page, $total_employees); ?></span>
                        of <span id="totalRecords"><?php echo $total_employees; ?></span> employees
                        <?php if (!empty($search_term) || !empty($dept_filter) || !empty($status_filter)): ?>
                            <span class="ml-2 text-blue-600">(filtered)</span>
                        <?php endif; ?>
                    </div>

                    <!-- Records per page selector -->
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-600">Show:</label>
                        <select id="perPageSelect" onchange="changePerPage(this.value)"
                            class="border border-gray-300 rounded-lg text-sm p-1.5">
                            <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                </div>

                <!-- Loading Spinner -->
                <div id="loadingSpinner" class="hidden text-center py-8">
                    <div class="loading-spinner"></div>
                    <p class="mt-2 text-gray-600">Searching employees...</p>
                </div>

                <!-- Employee Summary Table -->
                <div id="employeeTableContainer" class="table-container overflow-x-auto rounded-lg border border-gray-200">
                    <table class="w-full text-sm text-left text-gray-900" id="employeeSummaryTable">
                        <thead class="text-xs text-white uppercase bg-blue-600">
                            <tr>
                                <th scope="col" class="px-4 py-3">
                                    <input type="checkbox" id="selectAllEmployees"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                                </th>
                                <th scope="col" class="px-4 py-3">Employee ID</th>
                                <th scope="col" class="px-4 py-3">Name</th>
                                <th scope="col" class="px-4 py-3">Department</th>
                                <th scope="col" class="px-4 py-3">Employee Type</th>
                                <th scope="col" class="px-4 py-3">Total Records</th>
                                <th scope="col" class="px-4 py-3">Present Days</th>
                                <th scope="col" class="px-4 py-3">Total Hours</th>
                                <th scope="col" class="px-4 py-3">Last Attendance</th>
                                <th scope="col" class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="employeeTableBody" class="bg-white divide-y divide-gray-200">
                            <?php if (!empty($employee_summary)): ?>
                                <?php foreach ($employee_summary as $employee): ?>
                                    <tr class="bg-white hover:bg-gray-50 transition-colors duration-150">
                                        <td class="px-4 py-3">
                                            <input type="checkbox"
                                                class="employee-checkbox w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500"
                                                data-employee-id="<?php echo htmlspecialchars($employee['employee_id']); ?>"
                                                data-employee-name="<?php echo htmlspecialchars($employee['full_name']); ?>" <?php echo $employee['total_records'] > 0 ? '' : 'disabled'; ?>>
                                        </td>
                                        <td class="px-4 py-3 font-medium text-gray-900">
                                            <?php echo htmlspecialchars($employee['employee_id']); ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($employee['full_name']); ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            <?php echo htmlspecialchars($employee['department']); ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                                <?php
                                                $type = $employee['type'] ?? 'Unknown';
                                                if ($type == 'Permanent')
                                                    echo 'bg-green-100 text-green-800';
                                                elseif ($type == 'Job Order')
                                                    echo 'bg-blue-100 text-blue-800';
                                                elseif ($type == 'Contractual')
                                                    echo 'bg-purple-100 text-purple-800';
                                                else
                                                    echo 'bg-gray-100 text-gray-800';
                                                ?>">
                                                <?php echo $type; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if ($employee['total_records'] > 0): ?>
                                                <span
                                                    class="font-semibold text-gray-900"><?php echo $employee['total_records']; ?></span>
                                            <?php else: ?>
                                                <span class="text-gray-400">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if ($employee['present_days'] > 0): ?>
                                                <span
                                                    class="font-semibold text-green-600"><?php echo $employee['present_days']; ?></span>
                                            <?php else: ?>
                                                <span class="text-gray-400">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if ($employee['total_hours'] > 0): ?>
                                                <span
                                                    class="font-semibold text-blue-600"><?php echo round($employee['total_hours'], 1); ?>h</span>
                                                <?php if ($employee['total_ot'] > 0): ?>
                                                    <span class="text-xs text-orange-600 ml-1">(OT:
                                                        <?php echo round($employee['total_ot'], 1); ?>h)</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">0h</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            <?php echo $employee['last_date'] ? date('M d, Y', strtotime($employee['last_date'])) : 'No records'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex space-x-2 justify-center">
                                                <?php if ($employee['total_records'] > 0): ?>
                                                    <a href="?view_attendance=true&employee_id=<?php echo urlencode($employee['employee_id']); ?>"
                                                        class="action-btn view-btn" title="View Attendance Records">
                                                        <i class="fas fa-eye mr-1"></i> View
                                                    </a>
                                                <?php else: ?>
                                                    <button type="button"
                                                        onclick="showAddAttendancePrompt('<?php echo htmlspecialchars($employee['employee_id']); ?>', '<?php echo htmlspecialchars($employee['full_name']); ?>')"
                                                        class="action-btn bg-green-600 hover:bg-green-700 text-white border border-green-700"
                                                        title="Add Attendance Records">
                                                        <i class="fas fa-plus mr-1"></i> Add
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="noResultsRow">
                                    <td colspan='10' class='text-center py-8 text-gray-500'>
                                        <i class='fas fa-users text-4xl mb-2 text-gray-300'></i>
                                        <p>No employees found matching your criteria.</p>
                                        <?php if (!empty($search_term) || !empty($dept_filter) || !empty($status_filter)): ?>
                                            <p class="mt-2 text-sm text-blue-600">Try clearing your search filters or adjusting your
                                                criteria.</p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <!-- Pagination (exactly like employee summary) -->
                <!-- Pagination (exactly like employee summary) -->
                <?php if (isset($total_pages) && $total_pages > 1): ?>
                    <div id="attendancePaginationContainer" class="pagination-container mt-4">
                        <div class="pagination-info">
                            Page <span id="attCurrentPage"><?php echo $current_page; ?></span> of
                            <span id="attTotalPages"><?php echo $total_pages; ?></span>
                        </div>
                        <div class="pagination-nav" id="attendancePaginationNav">
                            <?php
                            // First page button
                            if ($current_page > 1) {
                                echo '<button onclick="changePage(1)" class="pagination-btn" title="First Page">
                        <i class="fas fa-angle-double-left"></i>
                      </button>';
                            } else {
                                echo '<button disabled class="pagination-btn" title="First Page">
                        <i class="fas fa-angle-double-left"></i>
                      </button>';
                            }

                            // Previous page button
                            if ($current_page > 1) {
                                echo '<button onclick="changePage(' . ($current_page - 1) . ')" class="pagination-btn" title="Previous Page">
                        <i class="fas fa-angle-left"></i>
                      </button>';
                            } else {
                                echo '<button disabled class="pagination-btn" title="Previous Page">
                        <i class="fas fa-angle-left"></i>
                      </button>';
                            }

                            // Calculate range of page numbers to show
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            // Show first page with ellipsis if needed
                            if ($start_page > 1) {
                                echo '<button onclick="changePage(1)" class="pagination-btn">1</button>';
                                if ($start_page > 2) {
                                    echo '<span class="pagination-ellipsis">...</span>';
                                }
                            }

                            // Show page numbers in range
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active_class = ($i == $current_page) ? 'active' : '';
                                echo "<button onclick=\"changePage($i)\" class=\"pagination-btn $active_class\">$i</button>";
                            }

                            // Show last page with ellipsis if needed
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="pagination-ellipsis">...</span>';
                                }
                                echo "<button onclick=\"changePage($total_pages)\" class=\"pagination-btn\">$total_pages</button>";
                            }

                            // Next page button
                            if ($current_page < $total_pages) {
                                echo '<button onclick="changePage(' . ($current_page + 1) . ')" class="pagination-btn" title="Next Page">
                        <i class="fas fa-angle-right"></i>
                      </button>';
                            } else {
                                echo '<button disabled class="pagination-btn" title="Next Page">
                        <i class="fas fa-angle-right"></i>
                      </button>';
                            }

                            // Last page button
                            if ($current_page < $total_pages) {
                                echo '<button onclick="changePage(' . $total_pages . ')" class="pagination-btn" title="Last Page">
                        <i class="fas fa-angle-double-right"></i>
                      </button>';
                            } else {
                                echo '<button disabled class="pagination-btn" title="Last Page">
                        <i class="fas fa-angle-double-right"></i>
                      </button>';
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Export Attendance Modal -->
    <div id="exportAttendanceModal" tabindex="-1" aria-hidden="true"
        class="fixed inset-0 z-50 hidden flex items-center justify-center p-4"
        style="background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
        <div
            class="relative w-full max-w-3xl max-h-[90vh] rounded-lg modal-animation modal-mobile-full overflow-y-auto">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between p-5  border-b rounded-t bg-green-600 text-white">
                    <h3 class="text-lg md:text-xl font-semibold">
                        <i class="fas fa-download mr-2"></i>Export Attendance Records
                    </h3>
                    <button type="button"
                        class="text-white bg-transparent hover:bg-green-700 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center transition duration-150 ease-in-out"
                        onclick="closeExportModal()">
                        <i class="fas fa-times w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-4 md:p-6 space-y-4">
                    <!-- Selected Employees Summary -->
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h4 class="text-sm font-semibold text-blue-800 mb-2 flex items-center">
                            <i class="fas fa-users mr-2"></i>Selected Employees
                        </h4>
                        <div id="selectedEmployeesSummary" class="text-sm text-gray-700">
                            <span id="selectedCount">0</span> employee(s) selected
                            <span id="selectedFromAllPages" class="ml-2 text-green-600 font-medium hidden">
                                (across all pages)
                            </span>
                        </div>
                        <div id="selectedEmployeesList"
                            class="mt-2 text-xs text-gray-600 max-h-24 overflow-y-auto hidden">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>

                    <!-- Date Range Filter -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="export_from_date" class="block mb-2 text-sm font-medium text-gray-900">From
                                Date</label>
                            <input type="date" id="export_from_date" name="export_from_date"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-green-500 focus:border-green-500 block w-full p-2.5">
                        </div>
                        <div>
                            <label for="export_to_date" class="block mb-2 text-sm font-medium text-gray-900">To
                                Date</label>
                            <input type="date" id="export_to_date" name="export_to_date"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-green-500 focus:border-green-500 block w-full p-2.5">
                        </div>
                    </div>

                    <!-- Department Filter -->
                    <div>
                        <label for="export_department" class="block mb-2 text-sm font-medium text-gray-900">Filter by
                            Department (Optional)</label>
                        <select id="export_department" name="export_department"
                            class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-green-500 focus:border-green-500 block w-full p-2.5">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>">
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Export Options -->
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Export Options</h4>
                        <div class="flex flex-wrap gap-4">
                            <label class="flex items-center cursor-pointer">
                                <input type="radio" name="export_format" id="format_excel" value="excel"
                                    class="w-4 h-4 text-green-600 bg-gray-100 border-gray-300 focus:ring-green-500"
                                    checked>
                                <span class="ml-2 text-sm font-medium text-gray-700">Excel Format (.xlsx) - Separate
                                    sheets per employee</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="radio" name="export_format" id="format_csv" value="csv"
                                    class="w-4 h-4 text-green-600 bg-gray-100 border-gray-300 focus:ring-green-500">
                                <span class="ml-2 text-sm font-medium text-gray-700">CSV Format (.csv) - Combined
                                    data</span>
                            </label>
                        </div>
                    </div>

                    <!-- Advanced Options -->
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Advanced Options</h4>
                        <div class="space-y-2">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" id="include_summary"
                                    class="w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500"
                                    checked>
                                <span class="ml-2 text-sm font-medium text-gray-700">Include summary row with totals per
                                    employee</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" id="include_employee_info"
                                    class="w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500"
                                    checked>
                                <span class="ml-2 text-sm font-medium text-gray-700">Include employee information
                                    header</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" id="format_time_12h"
                                    class="w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500">
                                <span class="ml-2 text-sm font-medium text-gray-700">Use 12-hour time format
                                    (AM/PM)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Summary Preview -->
                    <div id="exportSummary" class="export-summary hidden">
                        <h4 class="text-sm font-semibold text-blue-800 mb-2">Export Summary</h4>
                        <p id="exportSummaryText" class="text-sm text-gray-700"></p>
                    </div>

                    <!-- Loading Indicator -->
                    <div id="exportLoading" class="hidden text-center py-4">
                        <div class="loading-spinner"></div>
                        <p class="mt-2 text-sm text-gray-600">Preparing export...</p>
                    </div>

                    <!-- Error Message -->
                    <div id="exportError" class="hidden p-3 text-sm text-red-800 rounded-lg bg-red-50" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span id="exportErrorMessage"></span>
                    </div>
                </div>

                <div class="flex items-center justify-end p-4 md:p-6 space-x-3 border-t border-gray-200 rounded-b">
                    <input type="hidden" id="exportEmployeeIds" value="">
                    <input type="hidden" id="exportIsGlobal" value="0">
                    <button type="button" id="proceedExportBtn" onclick="proceedWithExport()"
                        class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:outline-none focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-download mr-2"></i>Proceed with Export
                    </button>
                    <button type="button" onclick="closeExportModal()"
                        class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-green-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly DTR Entry Modal (Keep exactly as original) -->
    <div id="bulkAddAttendanceModal" tabindex="-1" aria-hidden="true"
        class="fixed inset-0 z-50 hidden flex items-center justify-center p-4"
        style="background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
        <div class="relative w-full max-w-6xl max-h-[90vh] rounded-lg modal-animation overflow-y-auto">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between p-5 border-b rounded-t bg-yellow-600 text-white sticky top-0">
                    <h3 class="text-lg md:text-xl font-semibold">
                        <i class="fas fa-calendar-alt mr-2"></i>Monthly DTR Entry (Daily Time Records)
                    </h3>
                    <button type="button"
                        class="text-white bg-transparent hover:bg-yellow-700 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center transition duration-150 ease-in-out"
                        data-modal-hide="bulkAddAttendanceModal">
                        <i class="fas fa-times w-5 h-5"></i>
                    </button>
                </div>
                <form action="" method="POST" class="p-4 md:p-6 space-y-4" id="bulkAttendanceForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div>
                            <label for="bulk_employee_id" class="block mb-2 text-sm font-medium text-gray-900">Employee
                                ID *</label>
                            <input type="text" name="employee_id" id="bulk_employee_id" placeholder="Enter employee ID"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-yellow-500 focus:border-yellow-500 block w-full p-2.5"
                                required list="employeeListBulk">
                            <datalist id="employeeListBulk">
                                <?php foreach ($all_employees as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>">
                                        <?php echo htmlspecialchars($emp['full_name'] . ' - ' . $emp['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div>
                            <label for="bulk_employee_name"
                                class="block mb-2 text-sm font-medium text-gray-900">Employee Name *</label>
                            <input type="text" name="employee_name" id="bulk_employee_name"
                                placeholder="Enter employee name"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-yellow-500 focus:border-yellow-500 block w-full p-2.5"
                                required list="employeeNameListBulk">
                            <datalist id="employeeNameListBulk">
                                <?php foreach ($all_employees as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['full_name']); ?>">
                                        <?php echo htmlspecialchars($emp['employee_id'] . ' - ' . $emp['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div>
                            <label for="bulk_department" class="block mb-2 text-sm font-medium text-gray-900">Department
                                *</label>
                            <select name="department" id="bulk_department"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-yellow-500 focus:border-yellow-500 block w-full p-2.5"
                                required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>">
                                        <?php echo htmlspecialchars($dept); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="month_select"
                                    class="block mb-2 text-sm font-medium text-gray-900">Month</label>
                                <select id="month_select"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-yellow-500 focus:border-yellow-500 block w-full p-2.5">
                                    <?php
                                    $currentMonth = date('n');
                                    for ($i = 1; $i <= 12; $i++):
                                        $monthName = date('F', mktime(0, 0, 0, $i, 1));
                                    ?>
                                        <option value="<?php echo $i; ?>" <?php echo $i == $currentMonth ? 'selected' : ''; ?>>
                                            <?php echo $monthName; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div>
                                <label for="year_select"
                                    class="block mb-2 text-sm font-medium text-gray-900">Year</label>
                                <select id="year_select"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-yellow-500 focus:border-yellow-500 block w-full p-2.5">
                                    <?php
                                    $currentYear = date('Y');
                                    for ($year = $currentYear - 1; $year <= $currentYear + 1; $year++): ?>
                                        <option value="<?php echo $year; ?>" <?php echo $year == $currentYear ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Period Selection -->
                    <div class="bg-yellow-50 p-4 rounded-lg mb-4">
                        <h6 class="text-sm font-semibold text-yellow-800 mb-3 flex items-center">
                            <i class="fas fa-calendar-week mr-2"></i>DTR Period Coverage:
                        </h6>
                        <div class="flex flex-wrap items-center gap-6">
                            <label class="flex items-center cursor-pointer">
                                <input type="radio" name="dtr_period" id="period_first_half" value="first_half"
                                    class="w-4 h-4 text-yellow-600 bg-gray-100 border-gray-300 focus:ring-yellow-500"
                                    checked>
                                <span class="ml-2 text-sm font-medium text-gray-700">First Half (Days 1-15)</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="radio" name="dtr_period" id="period_second_half" value="second_half"
                                    class="w-4 h-4 text-yellow-600 bg-gray-100 border-gray-300 focus:ring-yellow-500">
                                <span class="ml-2 text-sm font-medium text-gray-700">Second Half (Days 16-30/31)</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="radio" name="dtr_period" id="period_full_month" value="full_month"
                                    class="w-4 h-4 text-yellow-600 bg-gray-100 border-gray-300 focus:ring-yellow-500">
                                <span class="ml-2 text-sm font-medium text-gray-700">Full Month</span>
                            </label>
                        </div>
                        <p class="text-xs text-yellow-700 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Select which period of the month you want to add attendance records for.
                        </p>
                    </div>

                    <!-- Quick Options -->
                    <div class="bg-blue-50 p-4 rounded-lg mb-4 border border-blue-200">
                        <h6 class="text-sm font-semibold text-blue-800 mb-3 flex items-center">
                            <i class="fas fa-bolt mr-2"></i>Quick Options
                        </h6>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" onclick="fillStandardTimes()"
                                class="quick-option-btn px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition duration-150 ease-in-out flex items-center shadow-sm">
                                <i class="fas fa-clock mr-2"></i>
                                Standard (8-12, 1-5)
                            </button>
                            <button type="button" onclick="fillEarlyTimes()"
                                class="quick-option-btn px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md transition duration-150 ease-in-out flex items-center shadow-sm">
                                <i class="fas fa-sun mr-2"></i>
                                Early (7:30-12, 1-5:30)
                            </button>
                            <button type="button" onclick="fillLateTimes()"
                                class="quick-option-btn px-3 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-md transition duration-150 ease-in-out flex items-center shadow-sm">
                                <i class="fas fa-moon mr-2"></i>
                                Late (8:30-12, 1-5:30)
                            </button>
                            <button type="button" onclick="clearAllTimes()"
                                class="quick-option-btn px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-md transition duration-150 ease-in-out flex items-center shadow-sm">
                                <i class="fas fa-trash-alt mr-2"></i>
                                Clear All
                            </button>
                            <button type="button" onclick="markWeekendsAsLeave()"
                                class="quick-option-btn px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md transition duration-150 ease-in-out flex items-center shadow-sm">
                                <i class="fas fa-calendar-times mr-2"></i>
                                Clear Weekends/Holidays
                            </button>
                            <button type="button" onclick="applyTemplateFromImage()"
                                class="quick-option-btn px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-md transition duration-150 ease-in-out flex items-center shadow-sm">
                                <i class="fas fa-file-alt mr-2"></i>
                                Apply Sample
                            </button>
                        </div>
                        <p class="text-xs text-blue-700 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Quick fill options only apply to work days (excludes weekends, holidays, and future dates).
                        </p>
                    </div>

                    <!-- Daily Time Entry Table -->
                    <div class="border-t pt-4">
                        <h6 class="text-base md:text-lg font-semibold text-yellow-600 mb-3 flex items-center">
                            <i class="fas fa-clock mr-2"></i>Daily Time Entry <span
                                class="text-sm font-normal text-gray-600 ml-2">(Enter times for each day)</span>
                        </h6>

                        <div class="bulk-table-container overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-900 min-w-[800px]">
                                <thead class="text-xs text-white uppercase bg-yellow-600">
                                    <tr>
                                        <th scope="col" class="px-3 py-2 text-center">Date</th>
                                        <th scope="col" class="px-3 py-2 text-center">Day</th>
                                        <th scope="col" class="px-3 py-2 text-center" colspan="2">AM</th>
                                        <th scope="col" class="px-3 py-2 text-center" colspan="2">PM</th>
                                        <th scope="col" class="px-3 py-2 text-center">Status</th>
                                    </tr>
                                    <tr>
                                        <th></th>
                                        <th></th>
                                        <th class="px-2 py-1 text-center bg-yellow-700">In</th>
                                        <th class="px-2 py-1 text-center bg-yellow-700">Out</th>
                                        <th class="px-2 py-1 text-center bg-yellow-700">In</th>
                                        <th class="px-2 py-1 text-center bg-yellow-700">Out</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="dailyTimeTable">
                                    <!-- Table will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 text-sm text-gray-600">
                            <p><i class="fas fa-info-circle text-yellow-500 mr-1"></i>
                                <strong>Note:</strong> Leave time fields empty for holidays, leaves, or rest days.
                                Empty fields will show <span class="font-mono bg-gray-100 px-1 py-0.5 rounded">--:--
                                    --</span>.
                                System will calculate OT and Undertime automatically.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end p-4 md:p-6 space-x-3 border-t border-gray-200 rounded-b">
                        <input type="hidden" name="start_date" id="hidden_start_date" value="">
                        <input type="hidden" name="end_date" id="hidden_end_date" value="">
                        <button type="submit" name="bulk_add_attendance"
                            class="text-white bg-yellow-600 hover:bg-yellow-700 focus:ring-4 focus:outline-none focus:ring-yellow-300 font-medium rounded-lg text-sm px-4 py-2.5 text-center flex items-center">
                            <i class="fas fa-calendar-plus mr-2"></i>Save Monthly DTR
                        </button>
                        <button type="button" data-modal-hide="bulkAddAttendanceModal"
                            class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-4 py-2.5 hover:text-gray-900">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Attendance Modal (Fixed Centering and Working) -->
    <div id="importAttendanceModal" tabindex="-1" aria-hidden="true"
        class="fixed inset-0 z-50 hidden flex items-center justify-center p-4"
        style="background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
        <div class="relative w-full max-w-md max-h-[90vh] rounded-lg modal-animation overflow-y-auto">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between p-5 border-b rounded-t bg-purple-600 text-white sticky top-0">
                    <h3 class="text-lg md:text-xl font-semibold">
                        <i class="fas fa-file-import mr-2"></i>Import Attendance Records
                    </h3>
                    <button type="button"
                        class="text-white bg-transparent hover:bg-purple-700 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center transition duration-150 ease-in-out close-import-modal">
                        <i class="fas fa-times w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-4 md:p-6">
                    <!-- XLSX Import Tab -->
                    <div id="xlsx" role="tabpanel">
                        <form id="xlsxImportForm" method="POST" enctype="multipart/form-data"
                            action="./import_attendance_xlsx.php">

                            <!-- Employee Selection Section -->
                            <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <h4 class="text-sm font-semibold text-blue-800 mb-3 flex items-center">
                                    <i class="fas fa-user mr-2 text-blue-600"></i>
                                    Select Employee
                                </h4>

                                <div class="space-y-3">
                                    <!-- Employee Search/Select -->
                                    <div>
                                        <label for="employee_search" class="block mb-1 text-xs font-medium text-gray-700">Search Employee</label>
                                        <input type="text" id="employee_search"
                                            class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                            placeholder="Type name or ID to search...">
                                        <div id="employee_search_results" class="hidden mt-1 max-h-40 overflow-y-auto bg-white border border-gray-300 rounded-lg shadow-lg">
                                            <!-- Search results will appear here -->
                                        </div>
                                    </div>

                                    <!-- Selected Employee Info -->
                                    <div id="selected_employee_info" class="hidden p-3 bg-green-50 border border-green-200 rounded-lg">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-xs text-gray-600">Selected Employee:</p>
                                                <p id="selected_employee_name" class="font-medium text-gray-900"></p>
                                                <p id="selected_employee_id" class="text-xs text-gray-600"></p>
                                                <p id="selected_employee_dept" class="text-xs text-gray-600"></p>
                                            </div>
                                            <button type="button" id="clear_employee_selection" class="text-red-600 hover:text-red-800 text-xs">
                                                <i class="fas fa-times"></i> Clear
                                            </button>
                                        </div>
                                    </div>


                                    <!-- Hidden field to store selected employee ID -->
                                    <input type="hidden" id="selected_employee_id_hidden" name="selected_employee_id" value="">
                                </div>
                            </div>

                            <!-- File Upload Area -->
                            <div class="mb-4">
                                <label for="xlsx_file" class="block mb-2 text-sm font-medium text-gray-900">Upload XLSX/DTR File *</label>
                                <div class="flex items-center justify-center w-full">
                                    <label for="xlsx_file"
                                        class="flex flex-col items-center justify-center w-full h-32 border-2 border-purple-300 border-dashed rounded-lg cursor-pointer bg-purple-50 hover:bg-purple-100 file-upload-area transition-all duration-200">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <i class="fas fa-file-excel text-3xl text-purple-600 mb-2"></i>
                                            <p class="mb-2 text-sm text-gray-700"><span
                                                    class="font-semibold text-purple-600">Click to upload</span> or
                                                drag and drop</p>
                                            <p class="text-xs text-gray-500">XLSX files from attendance system (max 10MB)</p>
                                        </div>
                                        <input id="xlsx_file" name="xlsx_file" type="file" class="hidden"
                                            accept=".xlsx,.xls" required />
                                    </label>
                                </div>
                            </div>

                            <!-- File Information with Remove Button -->
                            <div id="xlsxFileInfo"
                                class="hidden p-3 bg-purple-50 rounded-lg border border-purple-200 mb-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center min-w-0">
                                        <i class="fas fa-file-excel text-purple-600 mr-2 flex-shrink-0"></i>
                                        <div class="truncate">
                                            <span id="xlsxFileName"
                                                class="text-sm font-medium text-gray-700 block truncate"></span>
                                            <span id="xlsxFileSize" class="text-xs text-gray-500"></span>
                                        </div>
                                    </div>
                                    <button type="button" id="removeXlsxFile"
                                        class="ml-2 p-1.5 text-red-600 hover:text-red-800 hover:bg-red-100 rounded-full transition-colors duration-200 flex-shrink-0"
                                        title="Remove file">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Format Support Info -->
                            <div class="flex items-start p-3 text-sm text-purple-700 bg-purple-50 rounded-lg mb-4"
                                role="alert">
                                <i class="fas fa-info-circle mr-2 mt-0.5 flex-shrink-0"></i>
                                <div>
                                    <span class="font-medium">Format Support:</span>
                                    <ul class="mt-1.5 ml-4 list-disc text-xs space-y-1">
                                        <li>Multiple employees in one file</li>
                                        <li>Daily time records with AM/PM in/out</li>
                                        <li>Automatically skips duplicates</li>
                                        <li>Calculates OT and undertime automatically</li>
                                        <li>Handles weekends and holidays</li>
                                        <li class="font-semibold text-purple-800">✓ Manual employee selection for accuracy</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div id="xlsxProgressContainer" class="hidden mb-4">
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium text-purple-700">Importing...</span>
                                    <span id="xlsxProgressPercent"
                                        class="text-sm font-medium text-purple-700">0%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div id="xlsxProgressBar" class="bg-purple-600 h-2.5 rounded-full"
                                        style="width: 0%"></div>
                                </div>
                                <p id="xlsxStatusMessage" class="mt-2 text-xs text-gray-600"></p>
                            </div>

                            <!-- Import Errors (if any) -->
                            <div id="importErrors" class="hidden mb-4">
                                <!-- Will be populated by JavaScript -->
                            </div>

                            <div class="flex items-center justify-end space-x-3 mt-6 pt-4 border-t">
                                <button type="submit" id="importXlsxBtn"
                                    class="text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fas fa-upload mr-2"></i>
                                    <span>Import XLSX</span>
                                </button>
                                <button type="button" class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-purple-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 close-import-modal">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Results Modal (Fixed Centering) -->
    <div id="importResultsModal" tabindex="-1" aria-hidden="true"
        class="fixed inset-0 z-50 hidden flex items-center justify-center p-4"
        style="background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
        <div class="relative w-full max-w-2xl max-h-[90vh] rounded-lg modal-animation overflow-y-auto">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div id="importResultsHeader"
                    class="flex items-center justify-between p-5 border-b rounded-t bg-green-600 text-white sticky top-0">
                    <h3 class="text-lg md:text-xl font-semibold">
                        <i class="fas fa-check-circle mr-2"></i>Import Results
                    </h3>
                    <button type="button" onclick="closeAndReloadImportResults()"
                        class="text-white bg-transparent hover:bg-opacity-80 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center close-results-modal">
                        <i class="fas fa-times w-5 h-5"></i>
                    </button>
                </div>
                <div class="p-6" id="importResultsContent">
                    <!-- Results will be populated by JavaScript -->
                </div>
                <div class="flex items-center justify-between p-6 pt-0 border-t">
                    <div class="text-sm text-gray-600" id="reloadMessage">
                        <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                        Review the results below. Click Reload to refresh the page.
                    </div>
                    <div class="flex space-x-3">
                        <button type="button" id="reloadPageBtn"
                            onclick="reloadPageAfterImport()"
                            class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:outline-none focus:ring-green-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center flex items-center">
                            <i class="fas fa-sync-alt mr-2"></i>
                            Reload Page
                        </button>
                        <button type="button" onclick="closeAndReloadImportResults()"
                            class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-purple-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 close-results-modal">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Attendance Modal (Fixed Centering) -->
    <div id="editAttendanceModal" tabindex="-1" aria-hidden="true"
        class="fixed inset-0 z-50 hidden flex items-center justify-center p-4"
        style="background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
        <div class="relative w-full max-w-2xl max-h-[90vh] rounded-lg modal-animation overflow-y-auto">
            <div class="relative bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between p-5 border-b rounded-t bg-blue-600 text-white sticky top-0">
                    <h3 class="text-lg md:text-xl font-semibold">
                        <i class="fas fa-edit mr-2"></i>Edit Attendance Record
                    </h3>
                    <button type="button"
                        class="text-white bg-transparent hover:bg-blue-700 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center transition duration-150 ease-in-out close-edit-modal"
                        onclick="closeEditModal()">
                        <i class="fas fa-times w-5 h-5"></i>
                    </button>
                </div>
                <div id="editFormContent" class="p-4 md:p-6">
                    <div class="text-center py-8">
                        <div class="spinner-border inline-block w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full"
                            role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-2 text-gray-600">Loading record data...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript (Keep exactly as original with additions) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.1.1/flowbite.min.js"></script>
    <script>
        // ============================================
        // GLOBAL VARIABLES
        // ============================================
        let searchTimeout;
        let currentPage = <?php echo isset($current_page) ? $current_page : 1; ?>;
        let totalPages = <?php echo isset($total_pages) ? $total_pages : 1; ?>;
        let recordsPerPage = <?php echo isset($records_per_page) ? $records_per_page : 10; ?>;

        // Selection storage - this will persist across page changes
        let selectedEmployees = [];
        let globalSelectedEmployees = [];
        let isGlobalSelection = false;

        // ============================================
        // INITIALIZATION
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            // Load saved selections from storage
            loadSelectionsFromStorage();

            // Attach clear selection button event listener
            const clearBtn = document.getElementById('clearGlobalSelection');
            if (clearBtn) {
                clearBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    clearGlobalSelection();
                });
            }

            // Initialize import functionality
            initImportFunctionality();

            // Initialize modals
            initModals();

            // Initialize monthly DTR functionality
            initMonthlyDTR();

            // Initialize export functionality
            initExportFunctionality();

            // Initialize employee search
            initEmployeeSearch();

            // Update date and time
            updateDateTime();
            setInterval(updateDateTime, 1000);
        });

        // ============================================
        // EMPLOYEE SEARCH FUNCTIONALITY
        // ============================================
        function initEmployeeSearch() {
            const employeeSearch = document.getElementById('employee_search');
            const searchResults = document.getElementById('employee_search_results');
            const selectedEmployeeInfo = document.getElementById('selected_employee_info');
            const selectedEmployeeName = document.getElementById('selected_employee_name');
            const selectedEmployeeId = document.getElementById('selected_employee_id');
            const selectedEmployeeDept = document.getElementById('selected_employee_dept');
            const selectedEmployeeIdHidden = document.getElementById('selected_employee_id_hidden');
            const clearEmployeeBtn = document.getElementById('clear_employee_selection');
            const manualEmployeeId = document.getElementById('manual_employee_id');

            let searchTimeout;

            if (employeeSearch) {
                employeeSearch.addEventListener('input', function() {
                    const query = this.value.trim();

                    if (query.length < 2) {
                        if (searchResults) searchResults.classList.add('hidden');
                        return;
                    }

                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        // Show loading
                        if (searchResults) {
                            searchResults.innerHTML = '<div class="p-2 text-gray-500">Searching...</div>';
                            searchResults.classList.remove('hidden');
                        }

                        // Fetch employees from server
                        fetch(`get_employees.php?search=${encodeURIComponent(query)}`)
                            .then(response => response.json())
                            .then(data => {
                                if (searchResults) {
                                    if (data.employees && data.employees.length > 0) {
                                        let html = '';
                                        data.employees.forEach(emp => {
                                            html += `
                                        <div class="employee-result p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0 transition-colors duration-150"
                                             data-id="${emp.employee_id}"
                                             data-name="${emp.full_name}"
                                             data-dept="${emp.department}"
                                             data-type="${emp.type}">
                                            <div class="font-medium text-gray-900">${emp.full_name}</div>
                                            <div class="text-xs text-gray-600 mt-1">
                                                <span class="inline-block bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full mr-2">${emp.employee_id}</span>
                                                <span class="inline-block bg-gray-100 text-gray-800 px-2 py-0.5 rounded-full">${emp.department}</span>
                                                <span class="inline-block ml-2 ${emp.type === 'Permanent' ? 'bg-green-100 text-green-800' : emp.type === 'Job Order' ? 'bg-yellow-100 text-yellow-800' : 'bg-purple-100 text-purple-800'} px-2 py-0.5 rounded-full">${emp.type}</span>
                                            </div>
                                        </div>
                                    `;
                                        });
                                        searchResults.innerHTML = html;
                                        searchResults.classList.remove('hidden');

                                        // Add click handlers to results
                                        document.querySelectorAll('.employee-result').forEach(el => {
                                            el.addEventListener('click', function() {
                                                selectEmployee(
                                                    this.dataset.id,
                                                    this.dataset.name,
                                                    this.dataset.dept,
                                                    this.dataset.type
                                                );
                                            });
                                        });
                                    } else {
                                        searchResults.innerHTML = '<div class="p-4 text-gray-500 text-center">No employees found matching "<span class="font-medium">' + query + '</span>"</div>';
                                        searchResults.classList.remove('hidden');
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error searching employees:', error);
                                if (searchResults) {
                                    searchResults.innerHTML = '<div class="p-4 text-red-500 text-center">Error searching employees. Please try again.</div>';
                                    searchResults.classList.remove('hidden');
                                }
                            });
                    }, 300);
                });

                // Handle keyboard navigation
                employeeSearch.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        if (searchResults) searchResults.classList.add('hidden');
                    }
                });

                // Hide results when clicking outside
                document.addEventListener('click', function(e) {
                    if (searchResults && !employeeSearch.contains(e.target) && !searchResults.contains(e.target)) {
                        searchResults.classList.add('hidden');
                    }
                });
            }

            // Function to select an employee
            window.selectEmployee = function(id, name, dept, type) {
                if (selectedEmployeeName) selectedEmployeeName.textContent = name;
                if (selectedEmployeeId) selectedEmployeeId.textContent = `ID: ${id}`;
                if (selectedEmployeeDept) selectedEmployeeDept.textContent = `Department: ${dept} (${type})`;
                if (selectedEmployeeIdHidden) selectedEmployeeIdHidden.value = id;
                if (selectedEmployeeInfo) selectedEmployeeInfo.classList.remove('hidden');

                // Clear search
                if (employeeSearch) employeeSearch.value = '';
                if (searchResults) searchResults.classList.add('hidden');

                // Clear manual input
                if (manualEmployeeId) manualEmployeeId.value = '';

                showNotification(`Employee selected: ${name}`, 'success');
            };

            // Clear employee selection
            if (clearEmployeeBtn) {
                clearEmployeeBtn.addEventListener('click', function() {
                    if (selectedEmployeeInfo) selectedEmployeeInfo.classList.add('hidden');
                    if (selectedEmployeeIdHidden) selectedEmployeeIdHidden.value = '';
                    if (manualEmployeeId) manualEmployeeId.value = '';
                    if (employeeSearch) employeeSearch.value = '';
                    showNotification('Employee selection cleared', 'info');
                });
            }

            // Manual employee ID input
            if (manualEmployeeId) {
                manualEmployeeId.addEventListener('input', function() {
                    if (this.value.trim()) {
                        // Clear selected employee
                        if (selectedEmployeeInfo) selectedEmployeeInfo.classList.add('hidden');
                        if (selectedEmployeeIdHidden) selectedEmployeeIdHidden.value = this.value.trim();
                    }
                });

                // Validate on blur
                manualEmployeeId.addEventListener('blur', function() {
                    const id = this.value.trim();
                    if (id) {
                        // Optional: Validate if employee exists
                        fetch(`get_employees.php?search=${encodeURIComponent(id)}&exact=1`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.employees && data.employees.length > 0) {
                                    // Employee found, auto-select
                                    const emp = data.employees[0];
                                    selectEmployee(emp.employee_id, emp.full_name, emp.department, emp.type);
                                }
                            })
                            .catch(error => console.error('Error validating employee:', error));
                    }
                });
            }

            // Update form submission to validate employee selection
            const importForm = document.getElementById('xlsxImportForm');
            if (importForm) {
                importForm.addEventListener('submit', function(e) {
                    const employeeIdHidden = document.getElementById('selected_employee_id_hidden');
                    const manualId = document.getElementById('manual_employee_id');
                    const employeeId = employeeIdHidden ? employeeIdHidden.value : '';
                    const manualIdValue = manualId ? manualId.value.trim() : '';

                    if (!employeeId && !manualIdValue) {
                        e.preventDefault();
                        showNotification('Please select an employee or enter an Employee ID', 'error');
                        return false;
                    }

                    // If manual ID is entered, use that
                    if (manualIdValue && employeeIdHidden) {
                        employeeIdHidden.value = manualIdValue;
                    }

                    // Show loading state
                    const submitBtn = document.getElementById('importXlsxBtn');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Importing...';
                    }

                    return true;
                });
            }
        }

        // ============================================
        // MODAL INITIALIZATION
        // ============================================
        function initModals() {
            // Import modal buttons
            const importModalBtn = document.querySelector('[data-modal-target="importAttendanceModal"]');
            const importModal = document.getElementById('importAttendanceModal');

            if (importModalBtn && importModal) {
                importModalBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    importModal.classList.remove('hidden');
                    importModal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                });
            }

            // Close modal buttons
            document.querySelectorAll('.close-import-modal').forEach(btn => {
                btn.addEventListener('click', function() {
                    const modal = document.getElementById('importAttendanceModal');
                    if (modal) {
                        modal.classList.add('hidden');
                        modal.setAttribute('aria-hidden', 'true');
                        document.body.style.overflow = '';

                        // Reset form
                        const form = document.getElementById('xlsxImportForm');
                        if (form) form.reset();

                        // Reset employee selection
                        const selectedInfo = document.getElementById('selected_employee_info');
                        const selectedIdHidden = document.getElementById('selected_employee_id_hidden');
                        const searchInput = document.getElementById('employee_search');
                        const manualInput = document.getElementById('manual_employee_id');

                        if (selectedInfo) selectedInfo.classList.add('hidden');
                        if (selectedIdHidden) selectedIdHidden.value = '';
                        if (searchInput) searchInput.value = '';
                        if (manualInput) manualInput.value = '';

                        // Hide file info
                        hideFileInfo();

                        // Show sample file display
                        const sampleFileDisplay = document.getElementById('sampleFileDisplay');
                        if (sampleFileDisplay) sampleFileDisplay.classList.remove('hidden');

                        // Reset progress
                        const progressContainer = document.getElementById('xlsxProgressContainer');
                        if (progressContainer) progressContainer.classList.add('hidden');
                    }
                });
            });

            document.querySelectorAll('.close-results-modal').forEach(btn => {
                btn.addEventListener('click', function() {
                    const modal = document.getElementById('importResultsModal');
                    if (modal) {
                        modal.classList.add('hidden');
                        modal.setAttribute('aria-hidden', 'true');
                        document.body.style.overflow = '';
                    }
                });
            });

            // Click outside to close modals
            window.addEventListener('click', function(e) {
                if (e.target.classList.contains('fixed') && e.target.style.backgroundColor) {
                    const modals = ['importAttendanceModal', 'importResultsModal', 'exportAttendanceModal', 'bulkAddAttendanceModal', 'editAttendanceModal'];
                    modals.forEach(modalId => {
                        const modal = document.getElementById(modalId);
                        if (modal && !modal.classList.contains('hidden') && e.target === modal) {
                            modal.classList.add('hidden');
                            modal.setAttribute('aria-hidden', 'true');
                            document.body.style.overflow = '';
                        }
                    });
                }
            });

            // Escape key to close modals
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modals = ['importAttendanceModal', 'importResultsModal', 'exportAttendanceModal', 'bulkAddAttendanceModal', 'editAttendanceModal'];
                    modals.forEach(modalId => {
                        const modal = document.getElementById(modalId);
                        if (modal && !modal.classList.contains('hidden')) {
                            modal.classList.add('hidden');
                            modal.setAttribute('aria-hidden', 'true');
                            document.body.style.overflow = '';
                        }
                    });
                }
            });
        }

        // ============================================
        // MONTHLY DTR FUNCTIONS
        // ============================================
        function initMonthlyDTR() {
            const monthSelect = document.getElementById('month_select');
            const yearSelect = document.getElementById('year_select');

            if (monthSelect && yearSelect) {
                monthSelect.addEventListener('change', generateMonthTable);
                yearSelect.addEventListener('change', generateMonthTable);

                const periodFirstHalf = document.getElementById('period_first_half');
                const periodSecondHalf = document.getElementById('period_second_half');
                const periodFullMonth = document.getElementById('period_full_month');

                if (periodFirstHalf) periodFirstHalf.addEventListener('change', generateMonthTable);
                if (periodSecondHalf) periodSecondHalf.addEventListener('change', generateMonthTable);
                if (periodFullMonth) periodFullMonth.addEventListener('change', generateMonthTable);

                updateMonthYearOptions();
                generateMonthTable();
            }
        }

        function formatDate(date) {
            return date.toISOString().split('T')[0];
        }

        function formatDisplayDate(date) {
            const options = {
                month: 'short',
                day: 'numeric'
            };
            return date.toLocaleDateString('en-US', options);
        }

        function getDayName(dayOfWeek) {
            const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            return days[dayOfWeek];
        }

        function isFutureDate(date) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return date > today;
        }

        function generateMonthTable() {
            const monthSelect = document.getElementById('month_select');
            const yearSelect = document.getElementById('year_select');
            const dailyTimeTable = document.getElementById('dailyTimeTable');
            const hiddenStartDate = document.getElementById('hidden_start_date');
            const hiddenEndDate = document.getElementById('hidden_end_date');
            const periodFirstHalf = document.getElementById('period_first_half');
            const periodSecondHalf = document.getElementById('period_second_half');
            const periodFullMonth = document.getElementById('period_full_month');

            if (!monthSelect || !yearSelect || !dailyTimeTable) return;

            const month = parseInt(monthSelect.value);
            const year = parseInt(yearSelect.value);

            let startDay = 1;
            let endDay = new Date(year, month, 0).getDate();

            if (periodFirstHalf && periodFirstHalf.checked) {
                endDay = 15;
            } else if (periodSecondHalf && periodSecondHalf.checked) {
                startDay = 16;
            }

            const startDate = new Date(year, month - 1, startDay);
            const endDate = new Date(year, month - 1, endDay);

            if (hiddenStartDate) hiddenStartDate.value = formatDate(startDate);
            if (hiddenEndDate) hiddenEndDate.value = formatDate(endDate);

            let tableHTML = '';

            for (let day = startDay; day <= endDay; day++) {
                const currentDate = new Date(year, month - 1, day);
                const dayOfWeek = currentDate.getDay();
                const dayName = getDayName(dayOfWeek);
                const dateString = formatDate(currentDate);
                const formattedDate = formatDisplayDate(currentDate);
                const isFuture = isFutureDate(currentDate);

                let rowClass = '';
                let status = 'Work Day';

                if (isFuture) {
                    rowClass = 'future-date';
                    status = 'Future Date';
                } else if (dayOfWeek === 0 || dayOfWeek === 6) {
                    rowClass = 'weekend-row';
                    status = 'Weekend';
                }

                const holidays = ['01-01', '04-09', '05-01', '06-12', '08-21', '08-26', '11-30', '12-25', '12-30'];
                const monthDay = String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');

                if (holidays.includes(monthDay)) {
                    rowClass = 'holiday-row';
                    status = 'Holiday';
                }

                tableHTML += `
        <tr class="${rowClass}">
            <td class="px-3 py-2 font-medium text-gray-900 text-center">${formattedDate}</td>
            <td class="px-3 py-2 text-gray-700 text-center">${dayName}</td>
            <td class="px-2 py-1 text-center">
                <input type="time" name="am_time_in[${dateString}]" 
                       class="time-input border border-gray-300 rounded p-1 text-sm" 
                       placeholder="--:-- --"
                       data-day="${day}"
                       ${isFuture ? 'disabled' : ''}>
            </td>
            <td class="px-2 py-1 text-center">
                <input type="time" name="am_time_out[${dateString}]" 
                       class="time-input border border-gray-300 rounded p-1 text-sm" 
                       placeholder="--:-- --"
                       data-day="${day}"
                       ${isFuture ? 'disabled' : ''}>
            </td>
            <td class="px-2 py-1 text-center">
                <input type="time" name="pm_time_in[${dateString}]" 
                       class="time-input border border-gray-300 rounded p-1 text-sm" 
                       placeholder="--:-- --"
                       data-day="${day}"
                       ${isFuture ? 'disabled' : ''}>
            </td>
            <td class="px-2 py-1 text-center">
                <input type="time" name="pm_time_out[${dateString}]" 
                       class="time-input border border-gray-300 rounded p-1 text-sm" 
                       placeholder="--:-- --"
                       data-day="${day}"
                       ${isFuture ? 'disabled' : ''}>
            </td>
            <td class="px-3 py-2 text-center text-sm ${rowClass ? 'text-gray-500' : 'text-green-600'}">
                ${status}
            </td>
        </tr>
        `;
            }

            dailyTimeTable.innerHTML = tableHTML;
        }

        function updateMonthYearOptions() {
            const monthSelect = document.getElementById('month_select');
            const yearSelect = document.getElementById('year_select');

            if (!monthSelect || !yearSelect) return;

            const now = new Date();
            const currentYear = now.getFullYear();
            const currentMonth = now.getMonth() + 1;

            if (yearSelect) {
                Array.from(yearSelect.options).forEach(option => {
                    const yearValue = parseInt(option.value);
                    if (yearValue > currentYear) {
                        option.disabled = true;
                        option.style.color = '#999';
                    }
                });
            }

            if (monthSelect && parseInt(yearSelect.value) === currentYear) {
                Array.from(monthSelect.options).forEach(option => {
                    const monthValue = parseInt(option.value);
                    if (monthValue > currentMonth) {
                        option.disabled = true;
                        option.style.color = '#999';
                    }
                });
            }
        }

        // ============================================
        // QUICK FILL FUNCTIONS
        // ============================================
        window.fillStandardTimes = function() {
            fillAllTimes('08:00', '12:00', '13:00', '17:00');
        };

        window.fillEarlyTimes = function() {
            fillAllTimes('07:30', '12:00', '13:00', '17:30');
        };

        window.fillLateTimes = function() {
            fillAllTimes('08:30', '12:00', '13:00', '17:30');
        };

        function fillAllTimes(amIn, amOut, pmIn, pmOut) {
            const rows = document.querySelectorAll('#dailyTimeTable tr');
            let count = 0;

            rows.forEach(row => {
                const isWeekend = row.classList.contains('weekend-row');
                const isHoliday = row.classList.contains('holiday-row');
                const isFuture = row.classList.contains('future-date');

                if (!isWeekend && !isHoliday && !isFuture) {
                    const inputs = row.querySelectorAll('input[type="time"]:not(:disabled)');
                    if (inputs.length >= 4) {
                        inputs[0].value = amIn;
                        inputs[1].value = amOut;
                        inputs[2].value = pmIn;
                        inputs[3].value = pmOut;
                        count++;
                    }
                }
            });

            showNotification(`Filled ${count} work day(s) with: AM ${amIn}-${amOut}, PM ${pmIn}-${pmOut}`, 'success');
        }

        window.clearAllTimes = function() {
            const timeInputs = document.querySelectorAll('#dailyTimeTable input[type="time"]:not(:disabled)');
            let count = 0;

            timeInputs.forEach(input => {
                if (input.value) {
                    input.value = '';
                    count++;
                }
            });

            if (count > 0) {
                showNotification(`Cleared ${count} time field${count !== 1 ? 's' : ''}`, 'success');
            } else {
                showNotification('No time entries to clear', 'info');
            }
        };

        window.markWeekendsAsLeave = function() {
            const rows = document.querySelectorAll('#dailyTimeTable tr.weekend-row, #dailyTimeTable tr.holiday-row');
            let count = 0;

            rows.forEach(row => {
                const inputs = row.querySelectorAll('input[type="time"]:not(:disabled)');
                inputs.forEach(input => {
                    if (input.value) {
                        input.value = '';
                        count++;
                    }
                });
            });

            showNotification(`Cleared ${count} time fields from weekends/holidays`, 'success');
        };

        window.applyTemplateFromImage = function() {
            const sampleData = {
                12: {
                    am_in: '07:45',
                    am_out: '12:07',
                    pm_in: '12:48',
                    pm_out: '17:02'
                },
                13: {
                    am_in: '07:55',
                    am_out: '12:03',
                    pm_in: '12:56',
                    pm_out: '17:04'
                },
                14: {
                    am_in: '07:42',
                    am_out: '12:03',
                    pm_in: '13:05',
                    pm_out: '17:02'
                },
                15: {
                    am_in: '07:54',
                    am_out: '12:04',
                    pm_in: '12:53',
                    pm_out: '17:04'
                },
                16: {
                    am_in: '07:57',
                    am_out: '12:04',
                    pm_in: '12:58',
                    pm_out: '17:05'
                },
                19: {
                    am_in: '07:49',
                    am_out: '12:03',
                    pm_in: '12:59',
                    pm_out: '17:04'
                },
                20: {
                    am_in: '07:44',
                    am_out: '12:05',
                    pm_in: '12:53',
                    pm_out: '17:04'
                },
                21: {
                    am_in: '07:49',
                    am_out: '12:04',
                    pm_in: '12:59',
                    pm_out: '17:03'
                },
                22: {
                    am_in: '07:46',
                    am_out: '12:04',
                    pm_in: '13:07',
                    pm_out: '17:04'
                },
                23: {
                    am_in: '07:37',
                    am_out: '12:05',
                    pm_in: '12:55',
                    pm_out: '17:05'
                },
                26: {
                    am_in: '07:52',
                    am_out: '12:06',
                    pm_in: '12:37',
                    pm_out: '17:03'
                },
                27: {
                    am_in: '07:52',
                    am_out: '12:04',
                    pm_in: '12:56',
                    pm_out: '17:03'
                },
                28: {
                    am_in: '07:47',
                    am_out: '12:06',
                    pm_in: '12:40',
                    pm_out: '17:01'
                },
                29: {
                    am_in: '07:41',
                    am_out: '12:04',
                    pm_in: '12:47',
                    pm_out: '17:02'
                },
                30: {
                    am_in: '07:50',
                    am_out: '12:03',
                    pm_in: '',
                    pm_out: ''
                }
            };

            clearAllTimes();

            let appliedCount = 0;
            Object.keys(sampleData).forEach(day => {
                const data = sampleData[day];
                const inputs = document.querySelectorAll(`#dailyTimeTable input[data-day="${day}"]:not(:disabled)`);

                if (inputs.length >= 4) {
                    if (data.am_in) inputs[0].value = data.am_in;
                    if (data.am_out) inputs[1].value = data.am_out;
                    if (data.pm_in) inputs[2].value = data.pm_in;
                    if (data.pm_out) inputs[3].value = data.pm_out;
                    appliedCount++;
                }
            });

            showNotification(`Applied sample template to ${appliedCount} days!`, 'success');
        };

        // ============================================
        // AUTO-FILL EMPLOYEE INFO
        // ============================================
        async function autoFillEmployeeInfo() {
            const bulkEmployeeIdInput = document.getElementById('bulk_employee_id');
            const bulkEmployeeNameInput = document.getElementById('bulk_employee_name');
            const bulkDepartmentSelect = document.getElementById('bulk_department');

            if (!bulkEmployeeIdInput || !bulkEmployeeNameInput || !bulkDepartmentSelect) {
                return;
            }

            bulkEmployeeIdInput.addEventListener('input', function() {
                if (this.value.trim() === '') {
                    bulkEmployeeNameInput.value = '';
                    bulkDepartmentSelect.value = '';
                    this.classList.remove('border-red-500', 'ring-red-500');
                }
            });

            bulkEmployeeIdInput.addEventListener('blur', async function() {
                const employeeId = this.value.trim();

                if (employeeId.length === 0) {
                    return;
                }

                this.classList.add('opacity-75', 'bg-gray-100');
                this.disabled = true;
                this.classList.remove('border-red-500', 'ring-red-500');

                try {
                    const response = await fetch('get_employee_info.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'employee_id=' + encodeURIComponent(employeeId)
                    });

                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

                    const data = await response.json();

                    if (data.success) {
                        bulkEmployeeNameInput.value = data.employee.full_name || '';

                        if (data.employee.department) {
                            let found = false;
                            for (let i = 0; i < bulkDepartmentSelect.options.length; i++) {
                                if (bulkDepartmentSelect.options[i].value === data.employee.department) {
                                    bulkDepartmentSelect.selectedIndex = i;
                                    found = true;
                                    break;
                                }
                            }

                            if (!found) {
                                for (let i = 0; i < bulkDepartmentSelect.options.length; i++) {
                                    if (bulkDepartmentSelect.options[i].value.toLowerCase().includes(data.employee.department.toLowerCase()) ||
                                        data.employee.department.toLowerCase().includes(bulkDepartmentSelect.options[i].value.toLowerCase())) {
                                        bulkDepartmentSelect.selectedIndex = i;
                                        break;
                                    }
                                }
                            }
                        }

                        showNotification('Employee information loaded successfully!', 'success');
                    } else {
                        bulkEmployeeNameInput.value = '';
                        bulkDepartmentSelect.value = '';
                        this.classList.add('border-red-500', 'ring-red-500');
                        showNotification(data.message || 'Employee ID not found. Please check and try again.', 'error');
                    }
                } catch (error) {
                    console.error('Error fetching employee info:', error);
                    bulkEmployeeNameInput.value = '';
                    bulkDepartmentSelect.value = '';
                    this.classList.add('border-red-500', 'ring-red-500');
                    showNotification('Error loading employee information. Please try again.', 'error');
                } finally {
                    this.classList.remove('opacity-75', 'bg-gray-100');
                    this.disabled = false;
                }
            });

            bulkEmployeeIdInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.blur();
                }
            });
        }

        autoFillEmployeeInfo();

        // ============================================
        // IMPORT FUNCTIONALITY
        // ============================================
        function initImportFunctionality() {
            const xlsxFileInput = document.getElementById('xlsx_file');
            const xlsxFileInfo = document.getElementById('xlsxFileInfo');
            const xlsxFileName = document.getElementById('xlsxFileName');
            const xlsxFileSize = document.getElementById('xlsxFileSize');
            const removeXlsxFileBtn = document.getElementById('removeXlsxFile');
            const importBtn = document.getElementById('importXlsxBtn');
            const importForm = document.getElementById('xlsxImportForm');

            const dropZone = document.querySelector('label[for="xlsx_file"]');

            if (dropZone && xlsxFileInput) {
                setupDragAndDrop(dropZone, xlsxFileInput);
            }

            if (xlsxFileInput) {
                xlsxFileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        handleFileSelection(file);
                    } else {
                        hideFileInfo();
                    }
                });
            }

            if (removeXlsxFileBtn) {
                removeXlsxFileBtn.addEventListener('click', function() {
                    removeSelectedFile();
                });
            }

            // Handle form submission with AJAX
            if (importForm) {
                importForm.addEventListener('submit', function(e) {
                    e.preventDefault(); // Prevent normal form submission

                    // Validate employee selection
                    const employeeIdHidden = document.getElementById('selected_employee_id_hidden');
                    const manualId = document.getElementById('manual_employee_id');
                    const employeeId = employeeIdHidden ? employeeIdHidden.value : '';
                    const manualIdValue = manualId ? manualId.value.trim() : '';

                    if (!employeeId && !manualIdValue) {
                        showNotification('Please select an employee or enter an Employee ID', 'error');
                        return false;
                    }

                    // If manual ID is entered, set it as the selected ID
                    if (manualIdValue && employeeIdHidden) {
                        employeeIdHidden.value = manualIdValue;
                    }

                    // Validate file
                    if (!xlsxFileInput.files || xlsxFileInput.files.length === 0) {
                        showNotification('Please select a file to upload', 'error');
                        return false;
                    }

                    // Show loading state
                    if (importBtn) {
                        importBtn.disabled = true;
                        importBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Importing...';
                    }

                    // Show progress container
                    const progressContainer = document.getElementById('xlsxProgressContainer');
                    if (progressContainer) {
                        progressContainer.classList.remove('hidden');
                    }

                    // Create FormData and submit via AJAX
                    const formData = new FormData(importForm);

                    fetch('import_attendance_xlsx.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            // Hide progress
                            if (progressContainer) {
                                progressContainer.classList.add('hidden');
                            }

                            // Reset button
                            if (importBtn) {
                                importBtn.disabled = false;
                                importBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Import XLSX';
                            }

                            // Show results
                            showImportResults(data);
                        })
                        .catch(error => {
                            console.error('Import error:', error);

                            // Hide progress
                            if (progressContainer) {
                                progressContainer.classList.add('hidden');
                            }

                            // Reset button
                            if (importBtn) {
                                importBtn.disabled = false;
                                importBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Import XLSX';
                            }

                            // Show error
                            showNotification('Error during import: ' + error.message, 'error');
                        });
                });
            }

            function setupDragAndDrop(dropZone, fileInput) {
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, preventDefaults, false);
                });

                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                ['dragenter', 'dragover'].forEach(eventName => {
                    dropZone.addEventListener(eventName, highlight, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, unhighlight, false);
                });

                function highlight(e) {
                    dropZone.classList.add('border-purple-600', 'bg-purple-100');
                }

                function unhighlight(e) {
                    dropZone.classList.remove('border-purple-600', 'bg-purple-100');
                }

                dropZone.addEventListener('drop', handleDrop, false);

                function handleDrop(e) {
                    const dt = e.dataTransfer;
                    const files = dt.files;

                    if (files.length > 0) {
                        fileInput.files = files;
                        const event = new Event('change', {
                            bubbles: true
                        });
                        fileInput.dispatchEvent(event);
                    }
                }
            }

            function handleFileSelection(file) {
                const maxSize = 10 * 1024 * 1024;

                if (file.size > maxSize) {
                    showNotification('File size exceeds 10MB limit. Please choose a smaller file.', 'error');
                    xlsxFileInput.value = '';
                    hideFileInfo();
                    return;
                }

                const validTypes = ['.xlsx', '.xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
                if (!validTypes.includes(file.type) && !file.name.match(/\.(xlsx|xls)$/)) {
                    showNotification('Please select a valid Excel file (.xlsx or .xls)', 'error');
                    xlsxFileInput.value = '';
                    hideFileInfo();
                    return;
                }

                showFileInfo(file);

                const sampleFileDisplay = document.getElementById('sampleFileDisplay');
                if (sampleFileDisplay) {
                    sampleFileDisplay.classList.add('hidden');
                }
            }

            function showFileInfo(file) {
                if (xlsxFileInfo && xlsxFileName && xlsxFileSize) {
                    xlsxFileName.textContent = file.name;

                    let sizeText = '';
                    if (file.size < 1024) {
                        sizeText = file.size + ' bytes';
                    } else if (file.size < 1048576) {
                        sizeText = (file.size / 1024).toFixed(2) + ' KB';
                    } else {
                        sizeText = (file.size / 1048576).toFixed(2) + ' MB';
                    }
                    xlsxFileSize.textContent = sizeText;

                    xlsxFileInfo.classList.remove('hidden');
                }
            }

            window.hideFileInfo = function() {
                if (xlsxFileInfo) {
                    xlsxFileInfo.classList.add('hidden');
                }
                if (xlsxFileName) xlsxFileName.textContent = '';
                if (xlsxFileSize) xlsxFileSize.textContent = '';
            };

            function removeSelectedFile() {
                if (xlsxFileInput) {
                    xlsxFileInput.value = '';
                    hideFileInfo();

                    const sampleFileDisplay = document.getElementById('sampleFileDisplay');
                    if (sampleFileDisplay) {
                        sampleFileDisplay.classList.remove('hidden');
                    }

                    showNotification('File removed successfully', 'info');
                }
            }
        }

        // ============================================
        // SHOW IMPORT RESULTS FUNCTION
        // ============================================
        window.showImportResults = function(data) {
            const modal = document.getElementById('importResultsModal');
            const header = document.getElementById('importResultsHeader');
            const content = document.getElementById('importResultsContent');
            const reloadBtn = document.getElementById('reloadPageBtn');

            if (!modal || !header || !content) {
                // Fallback if modal elements don't exist
                alert(`Import Results:\n\nSuccess: ${data.success}\nImported: ${data.imported || 0}\nDuplicates: ${data.duplicates || 0}\n${data.message || ''}`);

                // Reload the page after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
                return;
            }

            header.className = `flex items-center justify-between p-5 border-b rounded-t ${data.success ? 'bg-green-600' : 'bg-red-600'} text-white`;

            let html = `
        <div class="text-center mb-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full ${data.success ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'} mb-4">
                <i class="fas ${data.success ? 'fa-check-circle' : 'fa-exclamation-circle'} text-3xl"></i>
            </div>
            <h4 class="text-xl font-semibold ${data.success ? 'text-green-600' : 'text-red-600'} mb-2">
                ${data.success ? 'Import Successful!' : 'Import Failed'}
            </h4>
            <p class="text-gray-600">${data.message || ''}</p>
        </div>
        
        <div class="grid grid-cols-3 gap-4 mb-4">
            <div class="bg-green-50 p-3 rounded-lg text-center">
                <div class="text-2xl font-bold text-green-600">${data.imported || 0}</div>
                <div class="text-xs text-gray-600">Imported</div>
            </div>
            <div class="bg-yellow-50 p-3 rounded-lg text-center">
                <div class="text-2xl font-bold text-yellow-600">${data.duplicates || 0}</div>
                <div class="text-xs text-gray-600">Duplicates</div>
            </div>
            <div class="bg-red-50 p-3 rounded-lg text-center">
                <div class="text-2xl font-bold text-red-600">${data.errors || 0}</div>
                <div class="text-xs text-gray-600">Errors</div>
            </div>
        </div>
    `;

            // Show error messages if any
            if (data.error_messages && data.error_messages.length > 0) {
                html += `
            <div class="mt-4">
                <h5 class="text-sm font-semibold text-red-700 mb-2 flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2 text-red-600"></i>
                    Errors
                </h5>
                <div class="max-h-32 overflow-y-auto bg-red-50 p-3 rounded-lg border border-red-200">
                    <ul class="list-disc pl-4 text-xs text-red-600 space-y-1">
                        ${data.error_messages.map(msg => `<li>${msg}</li>`).join('')}
                    </ul>
                </div>
            </div>
        `;
            }

            // Show imported records preview
            if (data.records && data.records.length > 0) {
                html += `
            <div class="mt-4">
                <h5 class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                    <i class="fas fa-check-circle mr-2 text-green-600"></i>
                    Recently Imported (first ${Math.min(data.records.length, 10)} of ${data.records.length})
                </h5>
                <div class="max-h-40 overflow-y-auto bg-gray-50 p-3 rounded-lg">
                    <table class="w-full text-xs">
                        <thead class="text-gray-600 border-b border-gray-200">
                            <tr>
                                <th class="text-left pb-2">Date</th>
                                <th class="text-left pb-2">AM In</th>
                                <th class="text-left pb-2">AM Out</th>
                                <th class="text-left pb-2">PM In</th>
                                <th class="text-left pb-2">PM Out</th>
                                <th class="text-left pb-2">Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.records.slice(0, 10).map(rec => `
                                <tr class="border-t border-gray-200">
                                    <td class="py-1">${rec.date || ''}</td>
                                    <td class="py-1">${rec.am_in ? rec.am_in.substring(0,5) : '--'}</td>
                                    <td class="py-1">${rec.am_out ? rec.am_out.substring(0,5) : '--'}</td>
                                    <td class="py-1">${rec.pm_in ? rec.pm_in.substring(0,5) : '--'}</td>
                                    <td class="py-1">${rec.pm_out ? rec.pm_out.substring(0,5) : '--'}</td>
                                    <td class="py-1 text-green-600">${rec.total_hours || 0}h</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
            }

            content.innerHTML = html;

            // Setup reload button
            if (reloadBtn) {
                // Remove existing event listeners
                const newReloadBtn = reloadBtn.cloneNode(true);
                reloadBtn.parentNode.replaceChild(newReloadBtn, reloadBtn);

                newReloadBtn.onclick = function(e) {
                    e.preventDefault();
                    reloadPageAfterImport();
                };
            }

            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            // Close the import modal
            const importModal = document.getElementById('importAttendanceModal');
            if (importModal) {
                importModal.classList.add('hidden');
                importModal.setAttribute('aria-hidden', 'true');
            }

            // Reset the import form
            const importForm = document.getElementById('xlsxImportForm');
            if (importForm) importForm.reset();
            hideFileInfo();

            // Auto-reload after 5 seconds on success
            if (data.success && data.imported > 0) {
                setTimeout(() => {
                    if (confirm('Import completed! Reload page to see the new records?')) {
                        window.location.reload();
                    }
                }, 5000);
            }
        };


        // ============================================
        // IMPORT RESULTS MODAL FUNCTIONS
        // ============================================

        // Function to close modal and reload page
        window.closeAndReloadImportResults = function() {
            const modal = document.getElementById('importResultsModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';

                // Show loading notification
                showNotification('Refreshing page...', 'info');

                // Reload the page after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 300);
            }
        };

        // Function specifically for reload button
        window.reloadPageAfterImport = function() {
            const reloadBtn = document.getElementById('reloadPageBtn');
            if (reloadBtn) {
                reloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Reloading...';
                reloadBtn.disabled = true;
            }

            // Show notification
            showNotification('Refreshing page to display updated records...', 'info');

            // Reload after short delay
            setTimeout(() => {
                window.location.reload();
            }, 500);
        };


        // ============================================
        // SELECTION MANAGEMENT FUNCTIONS
        // ============================================
        function saveSelectionsToStorage() {
            try {
                sessionStorage.setItem('attendanceSelectedEmployees', JSON.stringify(selectedEmployees));
                sessionStorage.setItem('attendanceGlobalSelected', JSON.stringify(globalSelectedEmployees));
                sessionStorage.setItem('attendanceIsGlobalSelection', isGlobalSelection ? 'true' : 'false');
            } catch (e) {
                console.error('Error saving selections to storage:', e);
            }
        }

        function loadSelectionsFromStorage() {
            try {
                const saved = sessionStorage.getItem('attendanceSelectedEmployees');
                if (saved) selectedEmployees = JSON.parse(saved);

                const savedGlobal = sessionStorage.getItem('attendanceGlobalSelected');
                if (savedGlobal) globalSelectedEmployees = JSON.parse(savedGlobal);

                const savedIsGlobal = sessionStorage.getItem('attendanceIsGlobalSelection');
                if (savedIsGlobal) isGlobalSelection = savedIsGlobal === 'true';

                setTimeout(() => {
                    updateCheckboxesFromSelection();
                    updateGlobalSelectionUI();
                }, 100);
            } catch (e) {
                console.error('Error loading selections from storage:', e);
            }
        }

        function updateCheckboxesFromSelection() {
            const checkboxes = document.querySelectorAll('.employee-checkbox:not(:disabled)');
            checkboxes.forEach(checkbox => {
                const empId = checkbox.dataset.employeeId;
                checkbox.checked = selectedEmployees.some(emp => emp.id === empId);
            });
            updateSelectAllCheckbox();
        }

        function updateSelectAllCheckbox() {
            const selectAll = document.getElementById('selectAllEmployees');
            if (!selectAll) return;

            const checkboxes = document.querySelectorAll('.employee-checkbox:not(:disabled)');
            const checkedCount = document.querySelectorAll('.employee-checkbox:checked:not(:disabled)').length;

            selectAll.checked = checkedCount > 0 && checkedCount === checkboxes.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
        }

        function updateSelectedEmployeesFromCheckboxes() {
            const checkboxes = document.querySelectorAll('.employee-checkbox:checked:not(:disabled)');
            const currentSelectionMap = new Map();

            checkboxes.forEach(checkbox => {
                currentSelectionMap.set(checkbox.dataset.employeeId, {
                    id: checkbox.dataset.employeeId,
                    name: checkbox.dataset.employeeName
                });
            });

            if (!isGlobalSelection) {
                const currentPageIds = new Set(Array.from(document.querySelectorAll('.employee-checkbox')).map(cb => cb.dataset.employeeId));

                selectedEmployees.forEach(emp => {
                    if (!currentPageIds.has(emp.id) && !currentSelectionMap.has(emp.id)) {
                        currentSelectionMap.set(emp.id, emp);
                    }
                });
            }

            selectedEmployees = Array.from(currentSelectionMap.values());
            saveSelectionsToStorage();
            updateSelectAllCheckbox();
            updateGlobalSelectionUI();
        }

        function updateGlobalSelectionUI() {
            const selectionInfo = document.getElementById('globalSelectionInfo');
            const selectedCountSpan = document.getElementById('globalSelectedCount');
            const selectedNamesSpan = document.getElementById('globalSelectedNames');
            const exportBtn = document.getElementById('exportBtn');

            if (isGlobalSelection && globalSelectedEmployees.length > 0) {
                selectedCountSpan.textContent = globalSelectedEmployees.length;
                const names = globalSelectedEmployees.slice(0, 3).map(e => e.name).join(', ');
                const moreCount = globalSelectedEmployees.length > 3 ? ` and ${globalSelectedEmployees.length - 3} more` : '';
                selectedNamesSpan.textContent = names + moreCount;
                selectionInfo.classList.remove('hidden');
                if (exportBtn) exportBtn.innerHTML = `<i class="fas fa-download mr-2"></i>Export (${globalSelectedEmployees.length} selected across all pages)`;
            } else if (selectedEmployees.length > 0) {
                selectionInfo.classList.remove('hidden');
                selectedCountSpan.textContent = selectedEmployees.length;
                selectedNamesSpan.textContent = '';
                if (exportBtn) exportBtn.innerHTML = `<i class="fas fa-download mr-2"></i>Export (${selectedEmployees.length} selected)`;
            } else {
                selectionInfo.classList.add('hidden');
                if (exportBtn) exportBtn.innerHTML = `<i class="fas fa-download mr-2"></i>Export`;
            }
        }

        function clearGlobalSelection() {
            globalSelectedEmployees = [];
            selectedEmployees = [];
            isGlobalSelection = false;

            document.querySelectorAll('.employee-checkbox').forEach(cb => cb.checked = false);
            updateSelectAllCheckbox();

            sessionStorage.removeItem('attendanceSelectedEmployees');
            sessionStorage.removeItem('attendanceGlobalSelected');
            sessionStorage.removeItem('attendanceIsGlobalSelection');

            updateGlobalSelectionUI();
            showNotification('Selection cleared', 'info');
        }

        // ============================================
        // EXPORT FUNCTIONALITY
        // ============================================
        function initExportFunctionality() {
            const exportBtn = document.getElementById('exportBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    openExportModal();
                });
            }

            document.getElementById('export_from_date')?.addEventListener('change', checkExportRecords);
            document.getElementById('export_to_date')?.addEventListener('change', checkExportRecords);
            document.getElementById('export_department')?.addEventListener('change', checkExportRecords);
        }

        function openExportModal() {
            let employeesToExport = [];
            let isGlobal = false;

            if (isGlobalSelection && globalSelectedEmployees.length > 0) {
                employeesToExport = globalSelectedEmployees;
                isGlobal = true;
            } else {
                employeesToExport = selectedEmployees;
            }

            if (employeesToExport.length === 0) {
                showNotification('Please select at least one employee to export.', 'error');
                return;
            }

            document.getElementById('export_from_date').value = '';
            document.getElementById('export_to_date').value = '';
            document.getElementById('export_department').value = '';
            document.getElementById('format_excel').checked = true;
            document.getElementById('include_summary').checked = true;
            document.getElementById('include_employee_info').checked = true;
            document.getElementById('format_time_12h').checked = false;
            document.getElementById('exportSummary').classList.add('hidden');
            document.getElementById('exportError').classList.add('hidden');

            document.getElementById('selectedCount').textContent = employeesToExport.length;

            const selectedFromAllPages = document.getElementById('selectedFromAllPages');
            if (isGlobal) {
                selectedFromAllPages.classList.remove('hidden');
            } else {
                selectedFromAllPages.classList.add('hidden');
            }

            const selectedList = document.getElementById('selectedEmployeesList');
            if (employeesToExport.length <= 10) {
                let listHtml = '<div class="font-medium mb-1">Selected employees:</div><ul class="list-disc pl-4">';
                employeesToExport.forEach(emp => {
                    listHtml += `<li>${emp.name} (${emp.id})</li>`;
                });
                listHtml += '</ul>';
                selectedList.innerHTML = listHtml;
                selectedList.classList.remove('hidden');
            } else {
                selectedList.classList.add('hidden');
            }

            const employeeIds = employeesToExport.map(e => e.id).join(',');
            document.getElementById('exportEmployeeIds').value = employeeIds;
            document.getElementById('exportIsGlobal').value = isGlobal ? '1' : '0';

            const modal = document.getElementById('exportAttendanceModal');
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            checkExportRecords();
        }

        function closeExportModal() {
            const modal = document.getElementById('exportAttendanceModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }
        }

        function checkExportRecords() {
            const fromDate = document.getElementById('export_from_date').value;
            const toDate = document.getElementById('export_to_date').value;
            const department = document.getElementById('export_department').value;
            const employeeIds = document.getElementById('exportEmployeeIds').value;

            if (!employeeIds) return;

            let url = `check_export_records.php?employee_ids=${encodeURIComponent(employeeIds)}`;
            if (fromDate) url += `&from_date=${encodeURIComponent(fromDate)}`;
            if (toDate) url += `&to_date=${encodeURIComponent(toDate)}`;
            if (department) url += `&department=${encodeURIComponent(department)}`;

            document.getElementById('exportLoading').classList.remove('hidden');
            document.getElementById('exportError').classList.add('hidden');

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('exportLoading').classList.add('hidden');

                    if (data.has_records) {
                        const employeeCount = data.employee_count || globalSelectedEmployees.length || selectedEmployees.length;
                        let summaryText = `${employeeCount} employee(s) selected`;
                        if (fromDate && toDate) {
                            summaryText += ` for period ${formatDateDisplay(fromDate)} to ${formatDateDisplay(toDate)}`;
                        } else if (fromDate) {
                            summaryText += ` from ${formatDateDisplay(fromDate)} onwards`;
                        } else if (toDate) {
                            summaryText += ` up to ${formatDateDisplay(toDate)}`;
                        }
                        if (department) summaryText += ` in ${department} department`;
                        summaryText += `. Found ${data.record_count || 0} records. Ready to export.`;

                        document.getElementById('exportSummaryText').textContent = summaryText;
                        document.getElementById('exportSummary').classList.remove('hidden');
                        document.getElementById('proceedExportBtn').disabled = false;
                    } else {
                        document.getElementById('exportErrorMessage').textContent = data.message || 'No attendance records found for the selected criteria.';
                        document.getElementById('exportError').classList.remove('hidden');
                        document.getElementById('proceedExportBtn').disabled = true;
                    }
                })
                .catch(error => {
                    document.getElementById('exportLoading').classList.add('hidden');
                    document.getElementById('exportErrorMessage').textContent = 'Error checking records. Please try again.';
                    document.getElementById('exportError').classList.remove('hidden');
                    document.getElementById('proceedExportBtn').disabled = true;
                    console.error('Error:', error);
                });
        }

        function formatDateDisplay(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        }

        function proceedWithExport() {
            const employeeIds = document.getElementById('exportEmployeeIds').value;
            const fromDate = document.getElementById('export_from_date').value;
            const toDate = document.getElementById('export_to_date').value;
            const department = document.getElementById('export_department').value;
            const format = document.querySelector('input[name="export_format"]:checked').value;
            const includeSummary = document.getElementById('include_summary').checked ? '1' : '0';
            const includeEmployeeInfo = document.getElementById('include_employee_info').checked ? '1' : '0';
            const formatTime12h = document.getElementById('format_time_12h').checked ? '1' : '0';

            let url = `export_multiple_attendance.php?employee_ids=${encodeURIComponent(employeeIds)}&format=${format}`;
            if (fromDate) url += `&from_date=${encodeURIComponent(fromDate)}`;
            if (toDate) url += `&to_date=${encodeURIComponent(toDate)}`;
            if (department) url += `&department=${encodeURIComponent(department)}`;
            url += `&include_summary=${includeSummary}&include_employee_info=${includeEmployeeInfo}&format_time_12h=${formatTime12h}`;

            const originalText = document.getElementById('proceedExportBtn').innerHTML;
            document.getElementById('proceedExportBtn').innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Exporting...';
            document.getElementById('proceedExportBtn').disabled = true;

            const link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            setTimeout(() => {
                document.getElementById('proceedExportBtn').innerHTML = originalText;
                document.getElementById('proceedExportBtn').disabled = false;
                closeExportModal();
                showNotification('Export started! Your download will begin shortly.', 'success');
            }, 1500);
        }

        // ============================================
        // GLOBAL SEARCH AND FILTER FUNCTIONS
        // ============================================
        const globalSearch = document.getElementById('global_search');
        const globalDepartment = document.getElementById('global_department');
        const globalStatus = document.getElementById('global_status');

        function performSearch() {
            if (searchTimeout) clearTimeout(searchTimeout);

            document.getElementById('loadingSpinner').classList.remove('hidden');
            document.getElementById('employeeTableContainer').classList.add('opacity-50');

            const search = globalSearch ? globalSearch.value : '';
            const department = globalDepartment ? globalDepartment.value : '';
            const status = globalStatus ? globalStatus.value : '';

            const params = new URLSearchParams({
                ajax_search: 'true',
                search: search,
                department: department,
                status_filter: status,
                page: currentPage,
                per_page: recordsPerPage
            });

            fetch(`attendance.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    updateEmployeeTable(data.employees);
                    totalPages = data.pages || 1;
                    document.getElementById('totalRecords').textContent = data.total || 0;

                    const from = data.total > 0 ? ((currentPage - 1) * recordsPerPage) + 1 : 0;
                    const to = data.total > 0 ? Math.min(currentPage * recordsPerPage, data.total) : 0;
                    document.getElementById('showingFrom').textContent = from;
                    document.getElementById('showingTo').textContent = to;

                    document.getElementById('loadingSpinner').classList.add('hidden');
                    document.getElementById('employeeTableContainer').classList.remove('opacity-50');

                    const url = new URL(window.location.href);
                    if (search) url.searchParams.set('search', search);
                    else url.searchParams.delete('search');
                    if (department) url.searchParams.set('department', department);
                    else url.searchParams.delete('department');
                    if (status) url.searchParams.set('status_filter', status);
                    else url.searchParams.delete('status_filter');
                    url.searchParams.set('page', currentPage);
                    url.searchParams.set('per_page', recordsPerPage);
                    url.searchParams.set('view', 'employees');
                    window.history.pushState({}, '', url.toString());

                    updateGlobalSelectionUI();
                })
                .catch(error => {
                    console.error('Search error:', error);
                    document.getElementById('loadingSpinner').classList.add('hidden');
                    document.getElementById('employeeTableContainer').classList.remove('opacity-50');
                    showNotification('Search completed but found no results', 'info');
                });
        }

        if (globalSearch) {
            globalSearch.addEventListener('input', function() {
                currentPage = 1;
                if (searchTimeout) clearTimeout(searchTimeout);
                searchTimeout = setTimeout(performSearch, 500);
            });
        }

        if (globalDepartment) {
            globalDepartment.addEventListener('change', function() {
                currentPage = 1;
                performSearch();
            });
        }

        if (globalStatus) {
            globalStatus.addEventListener('change', function() {
                currentPage = 1;
                performSearch();
            });
        }

        function updateEmployeeTable(employees) {
            const tbody = document.getElementById('employeeTableBody');

            if (!employees || employees.length === 0) {
                tbody.innerHTML = `
            <tr id="noResultsRow">
                <td colspan='10' class='text-center py-8 text-gray-500'>
                    <i class='fas fa-users text-4xl mb-2 text-gray-300'></i>
                    <p>No employees found matching your criteria.</p>
                    <p class="mt-2 text-sm text-blue-600">Try clearing your search filters or adjusting your criteria.</p>
                </td>
            </tr>
        `;
                return;
            }

            let html = '';
            employees.forEach(emp => {
                const typeClass = emp.type === 'Permanent' ? 'bg-green-100 text-green-800' :
                    emp.type === 'Job Order' ? 'bg-blue-100 text-blue-800' :
                    emp.type === 'Contractual' ? 'bg-purple-100 text-purple-800' :
                    'bg-gray-100 text-gray-800';

                const actionButton = emp.total_records > 0 ?
                    `<a href="?view_attendance=true&employee_id=${encodeURIComponent(emp.employee_id)}" class="action-btn view-btn" title="View Attendance Records">
                <i class="fas fa-eye mr-1"></i> View
            </a>` :
                    `<button type="button" onclick="showAddAttendancePrompt('${encodeURIComponent(emp.employee_id)}', '${emp.full_name.replace(/'/g, "\\'")}')" class="action-btn bg-green-600 hover:bg-green-700 text-white border border-green-700" title="Add Attendance Records">
                <i class="fas fa-plus mr-1"></i> Add
            </button>`;

                const lastDate = emp.last_date ? new Date(emp.last_date).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                }) : 'No records';

                const isChecked = isGlobalSelection ?
                    globalSelectedEmployees.some(e => e.id === emp.employee_id) :
                    selectedEmployees.some(e => e.id === emp.employee_id);

                html += `
        <tr class="bg-white hover:bg-gray-50 transition-colors duration-150">
            <td class="px-4 py-3">
                <input type="checkbox" class="employee-checkbox w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500" 
                       data-employee-id="${escapeHtml(emp.employee_id)}"
                       data-employee-name="${escapeHtml(emp.full_name)}"
                       ${emp.total_records > 0 ? '' : 'disabled'}
                       ${isChecked ? 'checked' : ''}>
            </td>
            <td class="px-4 py-3 font-medium text-gray-900">${escapeHtml(emp.employee_id)}</td>
            <td class="px-4 py-3"><div class="font-medium text-gray-900">${escapeHtml(emp.full_name)}</div></td>
            <td class="px-4 py-3 text-gray-700">${escapeHtml(emp.department)}</td>
            <td class="px-4 py-3"><span class="px-2 py-1 text-xs font-semibold rounded-full ${typeClass}">${escapeHtml(emp.type)}</span></td>
            <td class="px-4 py-3">${emp.total_records > 0 ? `<span class="font-semibold text-gray-900">${emp.total_records}</span>` : '<span class="text-gray-400">0</span>'}</td>
            <td class="px-4 py-3">${emp.present_days > 0 ? `<span class="font-semibold text-green-600">${emp.present_days}</span>` : '<span class="text-gray-400">0</span>'}</td>
            <td class="px-4 py-3">${emp.total_hours > 0 ? `<span class="font-semibold text-blue-600">${Math.round(emp.total_hours * 10) / 10}h</span>${emp.total_ot > 0 ? `<span class="text-xs text-orange-600 ml-1">(OT: ${Math.round(emp.total_ot * 10) / 10}h)</span>` : ''}` : '<span class="text-gray-400">0h</span>'}</td>
            <td class="px-4 py-3 text-gray-700">${lastDate}</td>
            <td class="px-4 py-3 text-center"><div class="flex space-x-2 justify-center">${actionButton}</div></td>
        </tr>
        `;
            });

            tbody.innerHTML = html;
            updateSelectAllCheckbox();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ============================================
        // UTILITY FUNCTIONS
        // ============================================
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

            if (dateElement) dateElement.textContent = now.toLocaleDateString('en-US', dateOptions);
            if (timeElement) timeElement.textContent = now.toLocaleTimeString('en-US', timeOptions);
        }

        window.showNotification = function(message, type = 'success') {
            const existingNotifications = document.querySelectorAll('.custom-notification');
            existingNotifications.forEach(notification => notification.remove());

            const notification = document.createElement('div');
            notification.className = `custom-notification fixed top-4 right-4 z-[9999] px-4 py-3 rounded-lg shadow-lg text-white ${type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'} transform transition-all duration-300 translate-y-0`;

            let icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';

            notification.innerHTML = `<div class="flex items-center"><i class="fas ${icon} mr-2"></i><span>${message}</span></div>`;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.transform = 'translateY(-100px)';
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        };

        window.showAddAttendancePrompt = function(employeeId, employeeName) {
            if (confirm(`No attendance records found for ${employeeName}. Would you like to add attendance records now?`)) {
                const modal = document.getElementById('bulkAddAttendanceModal');
                if (modal) {
                    document.getElementById('bulk_employee_id').value = employeeId;
                    setTimeout(() => {
                        const event = new Event('blur', {
                            bubbles: true
                        });
                        document.getElementById('bulk_employee_id').dispatchEvent(event);
                    }, 100);
                    modal.classList.remove('hidden');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                }
            }
        };

        // ============================================
        // SIDEBAR TOGGLE
        // ============================================
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarContainer = document.getElementById('sidebar-container');
        const sidebarOverlay = document.getElementById('sidebar-overlay');

        if (sidebarToggle && sidebarContainer && sidebarOverlay) {
            sidebarToggle.addEventListener('click', function() {
                sidebarContainer.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                document.body.style.overflow = sidebarContainer.classList.contains('active') ? 'hidden' : '';
            });

            sidebarOverlay.addEventListener('click', function() {
                sidebarContainer.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }

        // ============================================
        // PAYROLL DROPDOWN TOGGLE
        // ============================================
        const payrollToggle = document.getElementById('payroll-toggle');
        const payrollDropdown = document.getElementById('payroll-dropdown');

        if (payrollToggle && payrollDropdown) {
            payrollToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                payrollDropdown.classList.toggle('show');
                const chevron = this.querySelector('.chevron');
                if (chevron) chevron.classList.toggle('rotate');
            });

            document.addEventListener('click', function(event) {
                if (!payrollToggle.contains(event.target) && !payrollDropdown.contains(event.target)) {
                    payrollDropdown.classList.remove('show');
                    const chevron = payrollToggle.querySelector('.chevron');
                    if (chevron) chevron.classList.remove('rotate');
                }
            });
        }

        // ============================================
        // EDIT ATTENDANCE FUNCTIONS
        // ============================================
        window.editAttendance = function(attendanceId) {
            const modal = document.getElementById('editAttendanceModal');
            if (!modal) return;

            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            const editFormContent = document.getElementById('editFormContent');
            if (editFormContent) {
                editFormContent.innerHTML = `
            <div class="text-center py-8">
                <div class="spinner-border inline-block w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <p class="mt-2 text-gray-600">Loading record data...</p>
            </div>
        `;
            }

            fetch('get_attendance_record.php?id=' + attendanceId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const record = data.record;
                        const redirectParams = '<?php echo isset($current_view_params) ? $current_view_params : ''; ?>' + '&status=edit_success';

                        editFormContent.innerHTML = `
                    <form action="update_attendance.php" method="POST">
                        <input type="hidden" name="attendance_id" value="${record.id}">
                        <input type="hidden" name="redirect_params" value="${redirectParams}">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">Employee</label>
                                <input type="text" value="${record.employee_name}" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5" readonly>
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-900">Date *</label>
                                <input type="date" name="date" value="${record.date}" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            </div>
                        </div>
                        
                        <div class="border-t pt-4 mb-4">
                            <h6 class="text-lg font-semibold text-blue-600 mb-3">Morning Shift (AM)</h6>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-gray-900">Time-in</label>
                                    <input type="time" name="am_time_in" value="${record.am_time_in || ''}" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-gray-900">Time-out</label>
                                    <input type="time" name="am_time_out" value="${record.am_time_out || ''}" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                </div>
                            </div>
                        </div>
                        
                        <div class="border-t pt-4 mb-4">
                            <h6 class="text-lg font-semibold text-blue-600 mb-3">Afternoon Shift (PM)</h6>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-gray-900">Time-in</label>
                                    <input type="time" name="pm_time_in" value="${record.pm_time_in || ''}" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                </div>
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-gray-900">Time-out</label>
                                    <input type="time" name="pm_time_out" value="${record.pm_time_out || ''}" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button type="submit" class="text-white bg-green-600 hover:bg-green-700 font-medium rounded-lg text-sm px-5 py-2.5 text-center flex items-center">
                                <i class="fas fa-save mr-2"></i>Update Record
                            </button>
                            <button type="button" class="text-gray-500 bg-white hover:bg-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 border border-gray-300" onclick="closeEditModal()">
                                Cancel
                            </button>
                        </div>
                    </form>
                `;
                    } else {
                        editFormContent.innerHTML = `
                    <div class="text-center py-8 text-red-600">
                        <i class="fas fa-exclamation-triangle text-3xl mb-3"></i>
                        <p>Error loading record: ${data.message || 'Record not found'}</p>
                        <button type="button" class="mt-4 text-gray-500 bg-white hover:bg-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 border border-gray-300" onclick="closeEditModal()">
                            Close
                        </button>
                    </div>
                `;
                    }
                })
                .catch(error => {
                    editFormContent.innerHTML = `
                <div class="text-center py-8 text-red-600">
                    <i class="fas fa-exclamation-triangle text-3xl mb-3"></i>
                    <p>Network error. Please try again.</p>
                    <button type="button" class="mt-4 text-gray-500 bg-white hover:bg-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 border border-gray-300" onclick="closeEditModal()">
                        Close
                    </button>
                </div>
            `;
                });
        };

        window.closeEditModal = function() {
            const modal = document.getElementById('editAttendanceModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }
        };

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('close-edit-modal') ||
                e.target.closest('.close-edit-modal') ||
                (e.target.closest('button') && e.target.closest('button').hasAttribute('data-modal-hide') && e.target.closest('button').getAttribute('data-modal-hide') === 'editAttendanceModal')) {
                closeEditModal();
            }
        });

        // ============================================
        // DELETE ATTENDANCE FUNCTION
        // ============================================
        window.deleteAttendance = function(attendanceId, employeeName, date) {
            if (confirm(`Are you sure you want to delete the attendance record for ${employeeName} on ${date}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?php echo $_SERVER['PHP_SELF'] . (isset($current_view_params) ? $current_view_params : ''); ?>';

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'delete_attendance_id';
                idInput.value = attendanceId;

                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        };

        // ============================================
        // FUTURE DATE VALIDATION
        // ============================================
        function validateFutureDates() {
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                if (input.id === 'filter_from_date' || input.id === 'filter_to_date' || input.id === 'date' || input.id === 'edit_date') {
                    input.addEventListener('change', function() {
                        const selectedDate = new Date(this.value);
                        if (selectedDate > today) {
                            this.value = today.toISOString().split('T')[0];
                            showNotification('Future dates are not allowed', 'error');
                        }
                    });
                }
            });
        }

        validateFutureDates();

        // ============================================
        // FILTER FUNCTIONS
        // ============================================
        window.clearSearch = function() {
            if (globalSearch) {
                globalSearch.value = '';
                currentPage = 1;
                performSearch();
            }
        };

        window.clearDepartment = function() {
            if (globalDepartment) {
                globalDepartment.value = '';
                currentPage = 1;
                performSearch();
            }
        };

        window.clearStatus = function() {
            if (globalStatus) {
                globalStatus.value = '';
                currentPage = 1;
                performSearch();
            }
        };

        window.clearAllFilters = function() {
            if (globalSearch) globalSearch.value = '';
            if (globalDepartment) globalDepartment.value = '';
            if (globalStatus) globalStatus.value = '';
            currentPage = 1;
            performSearch();
        };

        window.changePage = function(page) {
            if (page < 1 || page > totalPages) return;

            const search = globalSearch ? globalSearch.value : '';
            const department = globalDepartment ? globalDepartment.value : '';
            const status = globalStatus ? globalStatus.value : '';

            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            url.searchParams.set('per_page', recordsPerPage);

            if (search) url.searchParams.set('search', search);
            else url.searchParams.delete('search');
            if (department) url.searchParams.set('department', department);
            else url.searchParams.delete('department');
            if (status) url.searchParams.set('status_filter', status);
            else url.searchParams.delete('status_filter');

            window.location.href = url.toString();
        };

        window.changePerPage = function(perPage) {
            recordsPerPage = parseInt(perPage);

            const url = new URL(window.location.href);
            url.searchParams.set('per_page', recordsPerPage);
            url.searchParams.set('page', 1);

            const search = globalSearch ? globalSearch.value : '';
            const department = globalDepartment ? globalDepartment.value : '';
            const status = globalStatus ? globalStatus.value : '';

            if (search) url.searchParams.set('search', search);
            else url.searchParams.delete('search');
            if (department) url.searchParams.set('department', department);
            else url.searchParams.delete('department');
            if (status) url.searchParams.set('status_filter', status);
            else url.searchParams.delete('status_filter');

            window.location.href = url.toString();
        };

        // ============================================
        // ATTENDANCE PAGINATION FUNCTIONS
        // ============================================
        window.changeAttendancePage = function(page) {
            const url = new URL(window.location.href);
            page = Math.max(1, parseInt(page) || 1);
            url.searchParams.set('att_page', page);

            const month = document.getElementById('monthFilter')?.value;
            const year = document.getElementById('yearFilter')?.value;

            if (month) url.searchParams.set('month', month);
            else url.searchParams.delete('month');
            if (year) url.searchParams.set('year', year);
            else url.searchParams.delete('year');

            const perPage = document.getElementById('attPerPage')?.value;
            if (perPage) url.searchParams.set('per_page', perPage);

            url.searchParams.set('view_attendance', 'true');
            window.location.href = url.toString();
        };

        window.changeAttendancePerPage = function(perPage) {
            const url = new URL(window.location.href);
            perPage = parseInt(perPage) || 10;
            const validValues = [10, 20, 50, 100];
            if (!validValues.includes(perPage)) perPage = 10;

            url.searchParams.set('per_page', perPage);
            url.searchParams.set('att_page', '1');

            const month = document.getElementById('monthFilter')?.value;
            const year = document.getElementById('yearFilter')?.value;

            if (month) url.searchParams.set('month', month);
            else url.searchParams.delete('month');
            if (year) url.searchParams.set('year', year);
            else url.searchParams.delete('year');

            url.searchParams.set('view_attendance', 'true');
            window.location.href = url.toString();
        };

        // ============================================
        // MONTH/YEAR FILTER FUNCTIONS
        // ============================================
        window.applyAttendanceFilter = function() {
            const month = document.getElementById('monthFilter').value;
            const year = document.getElementById('yearFilter').value;

            const url = new URL(window.location.href);
            if (month) url.searchParams.set('month', month);
            else url.searchParams.delete('month');
            if (year) url.searchParams.set('year', year);
            else url.searchParams.delete('year');

            url.searchParams.set('view_attendance', 'true');
            window.location.href = url.toString();
        };

        window.clearAttendanceFilter = function() {
            const url = new URL(window.location.href);
            url.searchParams.delete('month');
            url.searchParams.delete('year');
            url.searchParams.set('view_attendance', 'true');
            window.location.href = url.toString();
        };

        // ============================================
        // GLOBAL SELECT ALL BUTTON
        // ============================================
        const globalSelectAllBtn = document.getElementById('globalSelectAllBtn');
        if (globalSelectAllBtn) {
            globalSelectAllBtn.addEventListener('click', function() {
                selectAllEmployeesGlobally();
            });
        }

        function selectAllEmployeesGlobally() {
            showNotification('Loading all employees...', 'info');

            const search = document.getElementById('global_search')?.value || '';
            const department = document.getElementById('global_department')?.value || '';
            const status = document.getElementById('global_status')?.value || '';

            const params = new URLSearchParams({
                ajax_get_all_employees: true,
                search: search,
                department: department,
                status_filter: status
            });

            fetch(`attendance.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.employees.length > 0) {
                        globalSelectedEmployees = data.employees;
                        isGlobalSelection = true;
                        selectedEmployees = [];

                        updateGlobalSelectionUI();

                        document.querySelectorAll('.employee-checkbox').forEach(cb => cb.checked = false);
                        updateSelectAllCheckbox();

                        saveSelectionsToStorage();

                        showNotification(`Selected ${data.employees.length} employees across all pages`, 'success');
                    } else {
                        showNotification('No employees with attendance records found.', 'info');
                    }
                })
                .catch(error => {
                    console.error('Error fetching all employees:', error);
                    showNotification('Error selecting employees. Please try again.', 'error');
                });
        }

        // ============================================
        // SELECT ALL CHECKBOX HANDLER
        // ============================================
        const selectAllCheckbox = document.getElementById('selectAllEmployees');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                if (isGlobalSelection) clearGlobalSelection();

                const checkboxes = document.querySelectorAll('.employee-checkbox:not(:disabled)');
                checkboxes.forEach(checkbox => checkbox.checked = this.checked);

                updateSelectedEmployeesFromCheckboxes();
            });
        }

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('employee-checkbox')) {
                if (isGlobalSelection) clearGlobalSelection();
                updateSelectedEmployeesFromCheckboxes();
            }
        });

        // ============================================
        // REMOVE SAMPLE FILE FUNCTION
        // ============================================
        window.removeSampleFile = function() {
            const sampleFileDisplay = document.getElementById('sampleFileDisplay');
            const xlsxFileInput = document.getElementById('xlsx_file');

            if (sampleFileDisplay) sampleFileDisplay.classList.add('hidden');
            if (xlsxFileInput) {
                xlsxFileInput.value = '';
                hideFileInfo();
            }
            showNotification('Sample file removed', 'info');
        };
    </script>
</body>

</html>
<?php
// Close PDO connection
$pdo = null;
?>