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
            $records_per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10; // Changed from 20 to 10
// Validate records per page
            $valid_per_page = [10, 20, 50, 100]; // 10 is now first in the list
            if (!in_array($records_per_page, $valid_per_page)) {
                $records_per_page = 10; // Changed from 20 to 10
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

            // Debug log
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
        $all_employees = array_filter($all_employees, function ($emp) use ($search) {
            $search_lower = strtolower($search);
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
            'total_records' => $stats['total_records'],
            'present_days' => $stats['present_days'],
            'total_hours' => floatval($stats['total_hours']),
            'total_ot' => floatval($stats['total_ot']),
            'total_undertime' => floatval($stats['total_undertime']),
            'last_date' => $stats['last_date']
        ];
    }

    return [
        'employees' => $employees,
        'total' => $total_records,
        'pages' => ceil($total_records / $per_page)
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

    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $department = isset($_GET['department']) ? $_GET['department'] : '';
    $status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;

    $result = getEmployeeSummary($pdo, $search, $department, $status_filter, $page, $per_page);

    echo json_encode($result);
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
// --- Import Attendance Records ---
// =================================================================================

if (isset($_POST['import_attendance'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file'];
        $fileType = pathinfo($file['name'], PATHINFO_EXTENSION);

        $allowedExtensions = ['csv'];
        if (!in_array(strtolower($fileType), $allowedExtensions)) {
            $error_message = "Error: Only CSV files are allowed.";
        } else {
            $successCount = 0;
            $errorCount = 0;
            $duplicateCount = 0;
            $importErrors = [];

            try {
                $handle = fopen($file['tmp_name'], 'r');

                if ($handle !== FALSE) {
                    $header = fgetcsv($handle);
                    $rowNumber = 1;

                    while (($data = fgetcsv($handle)) !== FALSE) {
                        $rowNumber++;

                        if (empty(array_filter($data))) {
                            continue;
                        }

                        $date = !empty($data[0]) ? trim($data[0]) : '';
                        $employee_id = !empty($data[1]) ? trim($data[1]) : '';
                        $employee_name = !empty($data[2]) ? trim($data[2]) : '';
                        $department = !empty($data[3]) ? trim($data[3]) : '';
                        $am_time_in = !empty($data[4]) ? trim($data[4]) : null;
                        $am_time_out = !empty($data[5]) ? trim($data[5]) : null;
                        $pm_time_in = !empty($data[6]) ? trim($data[6]) : null;
                        $pm_time_out = !empty($data[7]) ? trim($data[7]) : null;

                        if (empty($date) || empty($employee_id) || empty($employee_name)) {
                            $errorCount++;
                            $importErrors[] = "Row $rowNumber: Missing required fields";
                            continue;
                        }

                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                            $errorCount++;
                            $importErrors[] = "Row $rowNumber: Invalid date format";
                            continue;
                        }

                        $check_sql = "SELECT id FROM attendance WHERE employee_id = ? AND date = ?";
                        $check_stmt = $pdo->prepare($check_sql);
                        $check_stmt->execute([$employee_id, $date]);

                        if ($check_stmt->rowCount() > 0) {
                            $duplicateCount++;
                            continue;
                        }

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
                            $successCount++;
                        } else {
                            $errorCount++;
                            $importErrors[] = "Row $rowNumber: Database error";
                        }
                    }
                    fclose($handle);
                }

                if ($successCount > 0) {
                    $success_message = "Import completed successfully! $successCount records imported.";
                    if ($duplicateCount > 0) {
                        $success_message .= " $duplicateCount duplicate records were skipped.";
                    }
                    if ($errorCount > 0) {
                        $error_message = "$errorCount records failed to import.";
                    }
                } else if ($duplicateCount > 0) {
                    $error_message = "All records already exist in the database. No new records were imported.";
                } else {
                    $error_message = "No valid records were imported. Please check your CSV file format.";
                }
            } catch (PDOException $e) {
                error_log("Import attendance error: " . $e->getMessage());
                $error_message = "Database error during import. Please try again.";
            } catch (Exception $e) {
                error_log("Import file error: " . $e->getMessage());
                $error_message = "Error processing file. Please check the file format.";
            }
        }
    } else {
        $error_message = "Error: Please select a file to upload.";
    }
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
        /* All existing styles remain exactly the same */
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
            font-family: 'Inter', sans-serif;
        }

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

        .modal-animation {
            animation: slideIn 0.2s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .btn-primary {
            background: linear-gradient(135deg, #1048cb 0%, #0C379D 100%);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 72, 203, 0.3);
        }

        .card-shadow {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }

        .navbar {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            height: 70px;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
            flex: 1;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover {
            transform: scale(1.02);
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
            font-weight: 700;
            color: white;
            line-height: 1.2;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .brand-subtitle {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .datetime-container {
            display: none;
            align-items: center;
            gap: 0.75rem;
        }

        @media (min-width: 768px) {
            .datetime-container {
                display: flex;
            }
        }

        .datetime-box {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            min-width: 140px;
        }

        .datetime-box:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .datetime-icon {
            font-size: 0.9rem;
            color: white;
            opacity: 0.9;
        }

        .datetime-text {
            display: flex;
            flex-direction: column;
        }

        .datetime-label {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .datetime-value {
            font-size: 0.85rem;
            color: white;
            font-weight: 600;
            line-height: 1.3;
        }

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
            padding: 0.4rem 0.6rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-button:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
        }

        .user-info {
            display: none;
            flex-direction: column;
            align-items: flex-start;
        }

        @media (min-width: 768px) {
            .user-info {
                display: flex;
            }
        }

        .user-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
            line-height: 1.2;
        }

        .user-role {
            font-size: 0.65rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        .user-chevron {
            font-size: 0.7rem;
            color: white;
            opacity: 0.8;
            transition: transform 0.3s ease;
        }

        .user-button.active .user-chevron {
            transform: rotate(180deg);
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 250px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            overflow: hidden;
        }

        .user-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-header {
            padding: 1rem;
            background: var(--gradient-primary);
            color: white;
        }

        .dropdown-header h3 {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .dropdown-header p {
            font-size: 0.75rem;
            opacity: 0.9;
        }

        .dropdown-menu {
            padding: 0.5rem;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #4b5563;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: #f3f4f6;
            color: var(--primary);
            transform: translateX(5px);
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            color: #9ca3af;
        }

        .dropdown-item:hover i {
            color: var(--primary);
        }

        .mobile-toggle {
            display: flex;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            width: 36px;
            height: 36px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        @media (min-width: 768px) {
            .mobile-toggle {
                display: none;
            }
        }

        .mobile-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }

        .mobile-toggle i {
            font-size: 1.1rem;
            color: white;
        }

        .sidebar-container {
            position: fixed;
            top: 70px;
            left: 0;
            height: calc(100vh - 70px);
            z-index: 40;
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            width: 260px;
        }

        .sidebar-container.active {
            transform: translateX(0);
        }

        @media (min-width: 768px) {
            .sidebar-container {
                transform: translateX(0);
                top: 0;
                height: 100vh;
                padding-top: 70px;
            }
        }

        .sidebar {
            width: 100%;
            height: 100%;
            background: var(--gradient-primary);
            box-shadow: 5px 0 25px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .sidebar-content {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .sidebar-overlay {
            position: fixed;
            top: 70px;
            left: 0;
            width: 100%;
            height: calc(100vh - 70px);
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 39;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        @media (min-width: 768px) {
            .sidebar-overlay {
                display: none;
            }
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .main-content {
            margin-top: 70px;
            padding: 1rem;
            transition: all 0.3s ease;
            min-height: calc(100vh - 70px);
            width: 100%;
        }

        @media (min-width: 768px) {
            .main-content {
                margin-left: 260px;
                width: calc(100% - 260px);
                padding: 1.5rem;
            }
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

        .sidebar-item .chevron {
            transition: transform 0.3s ease;
        }

        .sidebar-item .chevron.rotated {
            transform: rotate(180deg);
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
            padding: 0.5rem 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 0.25rem;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }

        .sidebar-dropdown-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-dropdown-item i {
            font-size: 0.75rem;
            margin-right: 0.5rem;
        }

        .mobile-brand {
            display: flex;
            align-items: center;
        }

        @media (min-width: 768px) {
            .mobile-brand {
                display: none;
            }
        }

        .mobile-brand-text {
            display: flex;
            flex-direction: column;
            margin-left: 0.5rem;
        }

        .mobile-brand-title {
            font-size: 1rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }

        .mobile-brand-subtitle {
            font-size: 0.65rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

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

        @media (max-width: 768px) {
            .mobile-table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin: 0 -1rem;
                width: calc(100% + 2rem);
            }

            .mobile-table {
                min-width: 800px;
            }

            .mobile-stack {
                flex-direction: column;
                width: 100%;
            }

            .mobile-stack>* {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .mobile-text-sm {
                font-size: 0.75rem;
            }

            .mobile-padding {
                padding: 1rem 0;
            }

            .mobile-full {
                width: 100%;
            }

            .mobile-hidden {
                display: none;
            }

            .modal-mobile-padding {
                padding: 1rem;
            }

            .modal-mobile-full {
                width: 95vw;
                margin: 0 auto;
            }
        }

        @media (max-width: 640px) {
            .responsive-grid {
                grid-template-columns: 1fr !important;
            }
        }

        @media (min-width: 641px) and (max-width: 1024px) {
            .responsive-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }

        .import-success {
            background-color: #dcfce7;
            border-left: 4px solid #22c55e;
        }

        .import-error {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
        }

        .import-warning {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
        }

        .file-upload-area {
            transition: all 0.3s ease;
        }

        .file-upload-area:hover {
            border-color: #3b82f6;
            background-color: #f8fafc;
        }

        .pagination-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.5rem;
            height: 2.5rem;
            padding: 0 0.75rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #4b5563;
            background-color: white;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }

        .pagination-link:hover {
            background-color: #f3f4f6;
            border-color: #9ca3af;
            color: #374151;
        }

        .pagination-link.active {
            background-color: #3b82f6;
            border-color: #3b82f6;
            color: white;
        }

        .pagination-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .time-input {
            width: 100px;
            text-align: center;
        }

        .bulk-table-container {
            max-height: 400px;
            overflow-y: auto;
        }

        .day-header {
            background-color: #f8fafc;
            font-weight: 600;
        }

        .weekend-row {
            background-color: #fef3c7;
        }

        .holiday-row {
            background-color: #fee2e2;
        }

        input[type="time"] {
            padding: 4px 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        input[type="time"]:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .quick-time-btn {
            padding: 2px 6px;
            font-size: 0.75rem;
            margin: 2px;
            border-radius: 3px;
            background-color: #f3f4f6;
            border: 1px solid #d1d5db;
            cursor: pointer;
        }

        .quick-time-btn:hover {
            background-color: #e5e7eb;
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

        #editAttendanceModal {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 9999;
        }

        #editAttendanceModal .modal-content {
            max-height: 90vh;
            overflow-y: auto;
        }

        .spinner-border {
            border-width: 3px;
            border-style: solid;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .quick-option-btn {
            transition: all 0.2s ease;
        }

        .quick-option-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Filter badge */
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

        /* Pagination Styles */
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

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-ellipsis {
            border: none;
            background: none;
            cursor: default;
            min-width: auto;
            padding: 0 0.25rem;
        }

        .pagination-ellipsis:hover {
            background: none;
            transform: none;
        }

        /* Records per page selector */
        .per-page-selector {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background-color: white;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
        }

        .per-page-selector select {
            border: none;
            background: transparent;
            font-size: 0.875rem;
            color: #374151;
            outline: none;
            cursor: pointer;
        }

        .per-page-selector select:focus {
            ring: none;
        }

        /* Loading Spinner */
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Search highlighting */
        .search-highlight {
            background-color: #fef3c7;
            padding: 0 2px;
            border-radius: 2px;
        }

        /* Export Modal Styles */
        .export-checkbox-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            background-color: #f9fafb;
        }

        .export-checkbox-item {
            display: flex;
            align-items: center;
            padding: 8px 10px;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }

        .export-checkbox-item:hover {
            background-color: #f0f7ff;
        }

        .export-checkbox-item:last-child {
            border-bottom: none;
        }

        .export-summary {
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
        }

        /* Global Select Styles */
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

        .global-select-badge i {
            font-size: 0.75rem;
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

        .selection-info-text {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
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

        /* Add to your existing styles */
        #clearGlobalSelection {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        #clearGlobalSelection:hover {
            background-color: #fecaca;
            color: #991b1b;
            border-color: #fca5a5;
        }

        /* Import modal improvements */
        .file-upload-area {
            transition: all 0.3s ease;
        }

        .file-upload-area:hover {
            border-color: #9333ea;
            background-color: #faf5ff;
        }

        .file-upload-area.border-purple-600 {
            border-width: 2px;
            transform: scale(1.01);
        }

        /* Remove button animations */
        #removeXlsxFile,
        #sampleFileDisplay button {
            transition: all 0.2s ease;
        }

        #removeXlsxFile:hover,
        #sampleFileDisplay button:hover {
            transform: scale(1.1);
            background-color: #fee2e2;
        }

        #removeXlsxFile:active,
        #sampleFileDisplay button:active {
            transform: scale(0.95);
        }

        /* File info animations */
        #xlsxFileInfo,
        #sampleFileDisplay {
            animation: slideDown 0.3s ease-out;
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

        /* Progress bar enhancements */
        #xlsxProgressBar {
            transition: width 0.3s ease;
        }

        /* Disabled state styling */
        #importXlsxBtn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Sample file badge */
        #sampleFileDisplay .bg-green-100 {
            font-size: 0.65rem;
            font-weight: 600;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
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

                <!-- Logo and Brand (Desktop) -->
                <a href="../dashboard.php" class="navbar-brand hidden md:flex">
                    <img class="brand-logo"
                        src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                        alt="Logo" />
                    <div class="brand-text">
                        <span class="brand-title">HR Management System</span>
                        <span class="brand-subtitle">Paluan Occidental Mindoro</span>
                    </div>
                </a>

                <!-- Logo and Brand (Mobile) -->
                <div class="mobile-brand">
                    <img class="brand-logo"
                        src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png"
                        alt="Logo" />
                    <div class="mobile-brand-text">
                        <span class="mobile-brand-title">HRMS</span>
                        <span class="mobile-brand-subtitle">Attendance</span>
                    </div>
                </div>
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

                <!-- User Menu -->
                <div class="user-menu">
                    <button id="user-menu-button" class="user-button">
                        <div class="user-info">
                            <span class="user-name">HR Administrator</span>
                            <span class="user-role">Administrator</span>
                        </div>
                        <i class="user-chevron fas fa-chevron-down"></i>
                    </button>
                    <div id="user-dropdown" class="user-dropdown">
                        <div class="dropdown-header">
                            <h3>HR Administrator</h3>
                            <p>Administrator</p>
                        </div>
                        <div class="dropdown-menu">
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i>
                                <span>My Profile</span>
                            </a>
                            <a href="settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i>
                                <span>Settings</span>
                            </a>
                            <a href="?logout=true" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <!-- Sidebar -->
    <div class="sidebar-container" id="sidebar-container">
        <div class="sidebar">
            <div class="sidebar-content">
                <!-- Dashboard -->
                <a href="dashboard.php" class="sidebar-item ">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard Analytics</span>
                </a>

                <!-- Employees -->
                <a href="./employees/Employee.php" class="sidebar-item">
                    <i class="fas fa-users"></i>
                    <span>Employees</span>
                </a>

                <!-- Attendance -->
                <a href="attendance.php" class="sidebar-item active">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>

                <!-- Payroll -->
                <a href=".payroll" class="sidebar-item" id="payroll-toggle">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Payroll</span>
                    <i class="fas fa-chevron-down chevron ml-auto"></i>
                </a>
                <div class="sidebar-dropdown-menu" id="payroll-dropdown">
                    <a href="Payrollmanagement/contractualpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Contractual
                    </a>
                    <a href="Payrollmanagement/joboerderpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Job Order
                    </a>
                    <a href="Payrollmanagement/permanentpayrolltable1.php" class="sidebar-dropdown-item">
                        <i class="fas fa-circle text-xs"></i>
                        Permanent
                    </a>
                </div>

                <!-- Salary -->
                <a href="sallarypayheads.php" class="sidebar-item">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>Salary Structure</span>
                </a>

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
        class="fixed top-0 left-0 right-0 z-50 hidden w-full p-4 md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative w-full max-w-6xl rounded-lg max-h-full modal-animation modal-mobile-full overflow-y-auto">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between p-5 border-b rounded-t bg-yellow-600 text-white">
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

    <!-- Import Attendance Modal (Updated with Remove Button) -->
    <div id="importAttendanceModal" tabindex="-1" aria-hidden="true"
        class="fixed top-0 left-0 right-0 z-50 hidden w-full p-4 md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative w-full max-w-md rounded-lg max-h-full modal-animation modal-mobile-full overflow-y-auto">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between p-5 border-b rounded-t bg-purple-600 text-white">
                    <h3 class="text-lg md:text-xl font-semibold">
                        <i class="fas fa-file-import mr-2"></i>Import Attendance Records
                    </h3>
                    <button type="button"
                        class="text-white bg-transparent hover:bg-purple-700 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center transition duration-150 ease-in-out"
                        data-modal-hide="importAttendanceModal">
                        <i class="fas fa-times w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-4 md:p-6 space-y-4">
                    <!-- Tab Navigation -->
                    <div class="border-b border-gray-200">
                        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center justify-center" id="importTab"
                            role="tablist">
                            <li class="mr-2" role="presentation">
                                <button
                                    class="inline-block p-4 border-b-2 rounded-t-lg active border-purple-600 text-purple-600"
                                    id="xlsx-tab" type="button" role="tab" aria-controls="xlsx" aria-selected="true">
                                    <i class="fas fa-file-excel mr-2 text-green-600"></i>XLSX/DTR Format
                                </button>
                            </li>
                        </ul>
                    </div>

                    <!-- XLSX Import Tab -->
                    <div class="p-4" id="xlsx" role="tabpanel" aria-labelledby="xlsx-tab">
                        <form id="xlsxImportForm" method="POST" enctype="multipart/form-data"
                            action="import_attendance_xlsx.php">
                            <div class="space-y-4">
                                <!-- File Upload Area -->
                                <div>
                                    <label for="xlsx_file" class="block mb-2 text-sm font-medium text-gray-900">Upload
                                        XLSX/DTR File *</label>
                                    <div class="flex items-center justify-center w-full">
                                        <label for="xlsx_file"
                                            class="flex flex-col items-center justify-center w-full h-32 border-2 border-purple-300 border-dashed rounded-lg cursor-pointer bg-purple-50 hover:bg-purple-100 file-upload-area transition-all duration-200">
                                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                <i class="fas fa-file-excel text-3xl text-purple-600 mb-2"></i>
                                                <p class="mb-2 text-sm text-gray-700"><span
                                                        class="font-semibold text-purple-600">Click to upload</span> or
                                                    drag and drop</p>
                                                <p class="text-xs text-gray-500">XLSX files from attendance system (max
                                                    10MB)</p>
                                            </div>
                                            <input id="xlsx_file" name="xlsx_file" type="file" class="hidden"
                                                accept=".xlsx,.xls" />
                                        </label>
                                    </div>
                                </div>

                                <!-- File Information with Remove Button -->
                                <div id="xlsxFileInfo"
                                    class="hidden p-3 bg-purple-50 rounded-lg border border-purple-200">
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

                                <!-- Sample File Display (for demonstration) -->
                                <div id="sampleFileDisplay" class="p-2 bg-green-50 border border-green-200 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <i class="fas fa-file-excel text-green-600 mr-2"></i>
                                            <span class="text-sm text-gray-700">JANUARY (1).xlsx</span>
                                            <span class="ml-2 text-xs text-gray-500">44.94 KB</span>
                                            <span
                                                class="ml-2 text-xs text-green-600 bg-green-100 px-2 py-0.5 rounded-full">Sample</span>
                                        </div>
                                        <button type="button" onclick="removeSampleFile()"
                                            class="text-red-600 hover:text-red-800 p-1 rounded-full hover:bg-red-100 transition-colors duration-200"
                                            title="Remove sample file">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Format Support Info -->
                                <div class="flex items-start p-3 text-sm text-purple-700 bg-purple-50 rounded-lg"
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
                                        </ul>
                                    </div>
                                </div>

                                <!-- Progress Bar -->
                                <div id="xlsxProgressContainer" class="hidden">
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
                                <div id="importErrors" class="hidden">
                                    <!-- Will be populated by JavaScript -->
                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-3 mt-6 pt-4 border-t">
                                <button type="submit" id="importXlsxBtn"
                                    class="text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fas fa-upload mr-2"></i>
                                    <span>Import XLSX</span>
                                </button>
                                <button type="button" data-modal-hide="importAttendanceModal"
                                    class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-purple-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Results Modal (Keep exactly as original) -->
    <div id="importResultsModal" tabindex="-1" aria-hidden="true"
        class="fixed top-0 left-0 right-0 z-50 hidden w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative w-full max-w-2xl max-h-full modal-animation">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div id="importResultsHeader"
                    class="flex items-center justify-between p-5 border-b rounded-t bg-green-600 text-white">
                    <h3 class="text-lg md:text-xl font-semibold">
                        <i class="fas fa-check-circle mr-2"></i>Import Results
                    </h3>
                    <button type="button"
                        class="text-white bg-transparent hover:bg-opacity-80 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center"
                        data-modal-hide="importResultsModal">
                        <i class="fas fa-times w-5 h-5"></i>
                    </button>
                </div>
                <div class="p-6" id="importResultsContent">
                    <!-- Results will be populated by JavaScript -->
                </div>
                <div class="flex items-center justify-end p-6 pt-0 border-t">
                    <button type="button" data-modal-hide="importResultsModal"
                        class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-purple-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Attendance Modal (Keep exactly as original) -->
    <div id="editAttendanceModal" tabindex="-1" aria-hidden="true"
        class="fixed top-0 left-0 right-0 z-50 hidden w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-[calc(100%-1rem)] max-h-full"
        style="background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
        <div class="relative w-full max-w-2xl max-h-full modal-animation modal-mobile-full modal-content mx-auto my-8">
            <div class="relative bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between p-5 border-b rounded-t bg-blue-600 text-white">
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
        let currentPage = <?php echo $current_page; ?>;
        let totalPages = <?php echo $total_pages; ?>;
        let recordsPerPage = <?php echo $records_per_page; ?>;

        // Selection storage - this will persist across page changes
        let selectedEmployees = [];
        let globalSelectedEmployees = [];
        let isGlobalSelection = false;

        // Load saved selections from sessionStorage on page load
        document.addEventListener('DOMContentLoaded', function () {
            // Load saved selections from storage
            loadSelectionsFromStorage();

            // Attach clear selection button event listener
            const clearBtn = document.getElementById('clearGlobalSelection');
            if (clearBtn) {
                // Remove any existing event listeners to avoid duplicates
                clearBtn.replaceWith(clearBtn.cloneNode(true));
                const newClearBtn = document.getElementById('clearGlobalSelection');
                newClearBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    clearGlobalSelection();
                });
            }

            // Initialize import functionality
            initImportFunctionality();
        });

        // ============================================
        // SELECTION MANAGEMENT FUNCTIONS
        // ============================================

        // Save selections to sessionStorage
        function saveSelectionsToStorage() {
            try {
                sessionStorage.setItem('attendanceSelectedEmployees', JSON.stringify(selectedEmployees));
                sessionStorage.setItem('attendanceGlobalSelected', JSON.stringify(globalSelectedEmployees));
                sessionStorage.setItem('attendanceIsGlobalSelection', isGlobalSelection ? 'true' : 'false');
                console.log('Saved to storage:', {
                    selected: selectedEmployees.length,
                    global: globalSelectedEmployees.length,
                    isGlobal: isGlobalSelection
                });
            } catch (e) {
                console.error('Error saving selections to storage:', e);
            }
        }

        // Load selections from sessionStorage
        function loadSelectionsFromStorage() {
            try {
                const saved = sessionStorage.getItem('attendanceSelectedEmployees');
                if (saved) {
                    selectedEmployees = JSON.parse(saved);
                }

                const savedGlobal = sessionStorage.getItem('attendanceGlobalSelected');
                if (savedGlobal) {
                    globalSelectedEmployees = JSON.parse(savedGlobal);
                }

                const savedIsGlobal = sessionStorage.getItem('attendanceIsGlobalSelection');
                if (savedIsGlobal) {
                    isGlobalSelection = savedIsGlobal === 'true';
                }

                console.log('Loaded from storage:', {
                    selected: selectedEmployees.length,
                    global: globalSelectedEmployees.length,
                    isGlobal: isGlobalSelection
                });

                // Update UI based on loaded selections
                setTimeout(() => {
                    updateCheckboxesFromSelection();
                    updateGlobalSelectionUI();
                }, 100);
            } catch (e) {
                console.error('Error loading selections from storage:', e);
            }
        }


        // Update checkboxes based on selectedEmployees array
        function updateCheckboxesFromSelection() {
            const checkboxes = document.querySelectorAll('.employee-checkbox:not(:disabled)');
            checkboxes.forEach(checkbox => {
                const empId = checkbox.dataset.employeeId;
                checkbox.checked = selectedEmployees.some(emp => emp.id === empId);
            });

            // Update select all checkbox
            updateSelectAllCheckbox();
        }

        // Update select all checkbox state
        function updateSelectAllCheckbox() {
            const selectAll = document.getElementById('selectAllEmployees');
            if (!selectAll) return;

            const checkboxes = document.querySelectorAll('.employee-checkbox:not(:disabled)');
            const checkedCount = document.querySelectorAll('.employee-checkbox:checked:not(:disabled)').length;

            selectAll.checked = checkedCount > 0 && checkedCount === checkboxes.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
        }

        // Update selectedEmployees array from checkboxes
        function updateSelectedEmployeesFromCheckboxes() {
            const checkboxes = document.querySelectorAll('.employee-checkbox:checked:not(:disabled)');

            // Create a Map to ensure uniqueness by employee ID
            const currentSelectionMap = new Map();

            // Add currently checked checkboxes
            checkboxes.forEach(checkbox => {
                currentSelectionMap.set(checkbox.dataset.employeeId, {
                    id: checkbox.dataset.employeeId,
                    name: checkbox.dataset.employeeName
                });
            });

            // If we have existing selections from other pages, merge them
            if (!isGlobalSelection) {
                // Keep selections from other pages that aren't on current page
                const currentPageIds = new Set(Array.from(document.querySelectorAll('.employee-checkbox')).map(cb => cb.dataset.employeeId));

                selectedEmployees.forEach(emp => {
                    if (!currentPageIds.has(emp.id) && !currentSelectionMap.has(emp.id)) {
                        // This employee is from another page and not currently selected on this page
                        // Keep them in selection
                        currentSelectionMap.set(emp.id, emp);
                    }
                });
            }

            // Convert Map back to array
            selectedEmployees = Array.from(currentSelectionMap.values());

            // Save to storage
            saveSelectionsToStorage();

            // Update UI
            updateSelectAllCheckbox();
            updateGlobalSelectionUI();
        }

        document.addEventListener('click', function (e) {
            // Handle clear selection button click
            if (e.target.closest('#clearGlobalSelection')) {
                e.preventDefault();
                clearGlobalSelection();
            }
        });

        // ============================================
        // HELPER FUNCTION TO SHOW ADD ATTENDANCE PROMPT
        // ============================================
        function showAddAttendancePrompt(employeeId, employeeName) {
            if (confirm(`No attendance records found for ${employeeName}. Would you like to add attendance records now?`)) {
                // Open the Monthly DTR Entry modal
                const modal = document.getElementById('bulkAddAttendanceModal');
                if (modal) {
                    // Pre-fill the employee ID and name
                    document.getElementById('bulk_employee_id').value = employeeId;

                    // Trigger auto-fill to get employee name and department
                    setTimeout(() => {
                        const event = new Event('blur', {
                            bubbles: true
                        });
                        document.getElementById('bulk_employee_id').dispatchEvent(event);
                    }, 100);

                    try {
                        const modalInstance = new Modal(modal);
                        modalInstance.show();
                    } catch (e) {
                        modal.classList.remove('hidden');
                        modal.setAttribute('aria-hidden', 'false');
                    }
                }
            }
        }

        // ============================================
        // UPDATE DATE AND TIME
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

            if (dateElement) {
                dateElement.textContent = now.toLocaleDateString('en-US', dateOptions);
            }
            if (timeElement) {
                timeElement.textContent = now.toLocaleTimeString('en-US', timeOptions);
            }
        }

        updateDateTime();
        setInterval(updateDateTime, 1000);

        // ============================================
        // SIDEBAR TOGGLE
        // ============================================

        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarContainer = document.getElementById('sidebar-container');
        const sidebarOverlay = document.getElementById('sidebar-overlay');

        if (sidebarToggle && sidebarContainer) {
            sidebarToggle.addEventListener('click', function () {
                sidebarContainer.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
            });

            sidebarOverlay.addEventListener('click', function () {
                sidebarContainer.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
        }

        // ============================================
        // USER MENU TOGGLE
        // ============================================

        const userMenuButton = document.getElementById('user-menu-button');
        const userDropdown = document.getElementById('user-dropdown');

        if (userMenuButton && userDropdown) {
            userMenuButton.addEventListener('click', function (e) {
                e.stopPropagation();
                userDropdown.classList.toggle('active');
                this.classList.toggle('active');
            });

            document.addEventListener('click', function (event) {
                if (!userMenuButton.contains(event.target) && !userDropdown.contains(event.target)) {
                    userDropdown.classList.remove('active');
                    userMenuButton.classList.remove('active');
                }
            });
        }

        // ============================================
        // PAYROLL DROPDOWN TOGGLE
        // ============================================

        const payrollToggle = document.getElementById('payroll-toggle');
        const payrollDropdown = document.getElementById('payroll-dropdown');

        if (payrollToggle && payrollDropdown) {
            payrollToggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                payrollDropdown.classList.toggle('open');

                const chevron = this.querySelector('.chevron');
                if (chevron) {
                    chevron.classList.toggle('rotated');
                }
            });
        }

        // ============================================
        // AUTO-FILL EMPLOYEE INFO
        // ============================================

        async function autoFillEmployeeInfo() {
            const bulkEmployeeIdInput = document.getElementById('bulk_employee_id');
            const bulkEmployeeNameInput = document.getElementById('bulk_employee_name');
            const bulkDepartmentSelect = document.getElementById('bulk_department');

            if (!bulkEmployeeIdInput || !bulkEmployeeNameInput || !bulkDepartmentSelect) {
                console.log('Employee form elements not found');
                return;
            }

            // Add input event for real-time validation
            bulkEmployeeIdInput.addEventListener('input', function () {
                // Clear the fields if input is empty
                if (this.value.trim() === '') {
                    bulkEmployeeNameInput.value = '';
                    // Reset department to default
                    bulkDepartmentSelect.value = '';

                    // Remove any error styling
                    this.classList.remove('border-red-500', 'ring-red-500');
                }
            });

            bulkEmployeeIdInput.addEventListener('blur', async function () {
                const employeeId = this.value.trim();

                if (employeeId.length === 0) {
                    return;
                }

                // Show loading state
                this.classList.add('opacity-75', 'bg-gray-100');
                this.disabled = true;

                // Clear previous error styling
                this.classList.remove('border-red-500', 'ring-red-500');

                try {
                    console.log('Fetching employee info for ID:', employeeId);

                    const response = await fetch('get_employee_info.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'employee_id=' + encodeURIComponent(employeeId)
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    console.log('Response data:', data);

                    if (data.success) {
                        // Success - populate the fields
                        bulkEmployeeNameInput.value = data.employee.full_name || '';

                        // Set department if it exists in the response
                        if (data.employee.department) {
                            // Try to find matching department in dropdown
                            let found = false;
                            for (let i = 0; i < bulkDepartmentSelect.options.length; i++) {
                                if (bulkDepartmentSelect.options[i].value === data.employee.department) {
                                    bulkDepartmentSelect.selectedIndex = i;
                                    found = true;
                                    break;
                                }
                            }

                            // If exact match not found, try to find partial match
                            if (!found) {
                                for (let i = 0; i < bulkDepartmentSelect.options.length; i++) {
                                    if (bulkDepartmentSelect.options[i].value.toLowerCase().includes(data.employee.department.toLowerCase()) ||
                                        data.employee.department.toLowerCase().includes(bulkDepartmentSelect.options[i].value.toLowerCase())) {
                                        bulkDepartmentSelect.selectedIndex = i;
                                        found = true;
                                        break;
                                    }
                                }
                            }

                            // If still not found, set a custom value? (but select doesn't support custom values)
                            if (!found) {
                                console.log('Department not found in dropdown:', data.employee.department);
                            }
                        }

                        showNotification('Employee information loaded successfully!', 'success');
                    } else {
                        // Error - clear fields and show error
                        bulkEmployeeNameInput.value = '';
                        bulkDepartmentSelect.value = '';

                        // Add error styling
                        this.classList.add('border-red-500', 'ring-red-500');

                        showNotification(data.message || 'Employee ID not found. Please check and try again.', 'error');
                    }
                } catch (error) {
                    console.error('Error fetching employee info:', error);

                    // Clear fields on error
                    bulkEmployeeNameInput.value = '';
                    bulkDepartmentSelect.value = '';

                    // Add error styling
                    this.classList.add('border-red-500', 'ring-red-500');

                    showNotification('Error loading employee information. Please try again.', 'error');
                } finally {
                    // Remove loading state
                    this.classList.remove('opacity-75', 'bg-gray-100');
                    this.disabled = false;
                }
            });

            // Allow Enter key to trigger the blur event
            bulkEmployeeIdInput.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.blur();
                }
            });
        }

        autoFillEmployeeInfo();

        // ============================================
        // MONTHLY DTR ENTRY FUNCTIONS
        // ============================================

        const monthSelect = document.getElementById('month_select');
        const yearSelect = document.getElementById('year_select');
        const dailyTimeTable = document.getElementById('dailyTimeTable');
        const hiddenStartDate = document.getElementById('hidden_start_date');
        const hiddenEndDate = document.getElementById('hidden_end_date');
        const periodFirstHalf = document.getElementById('period_first_half');
        const periodSecondHalf = document.getElementById('period_second_half');
        const periodFullMonth = document.getElementById('period_full_month');

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
            if (!monthSelect || !yearSelect) return;

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

            hiddenStartDate.value = formatDate(startDate);
            hiddenEndDate.value = formatDate(endDate);

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

        // ============================================
        // QUICK FILL FUNCTIONS
        // ============================================

        window.fillStandardTimes = function () {
            fillAllTimes('08:00', '12:00', '13:00', '17:00');
        };

        window.fillEarlyTimes = function () {
            fillAllTimes('07:30', '12:00', '13:00', '17:30');
        };

        window.fillLateTimes = function () {
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

        window.clearAllTimes = function () {
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

        window.markWeekendsAsLeave = function () {
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

        window.applyTemplateFromImage = function () {
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
        // ATTACH MONTHLY DTR EVENT LISTENERS
        // ============================================

        if (monthSelect) monthSelect.addEventListener('change', generateMonthTable);
        if (yearSelect) yearSelect.addEventListener('change', generateMonthTable);
        if (periodFirstHalf) periodFirstHalf.addEventListener('change', generateMonthTable);
        if (periodSecondHalf) periodSecondHalf.addEventListener('change', generateMonthTable);
        if (periodFullMonth) periodFullMonth.addEventListener('change', generateMonthTable);

        function updateMonthYearOptions() {
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

        if (monthSelect && yearSelect) {
            updateMonthYearOptions();
            generateMonthTable();
        }

        // ============================================
        // IMPROVED XLSX IMPORT FUNCTIONALITY WITH REMOVE BUTTON
        // ============================================

        function initImportFunctionality() {
            // Tab switching
            const xlsxTab = document.getElementById('xlsx-tab');
            const xlsxPanel = document.getElementById('xlsx');

            // File upload elements
            const xlsxFileInput = document.getElementById('xlsx_file');
            const xlsxFileInfo = document.getElementById('xlsxFileInfo');
            const xlsxFileName = document.getElementById('xlsxFileName');
            const xlsxFileSize = document.getElementById('xlsxFileSize');
            const removeXlsxFileBtn = document.getElementById('removeXlsxFile');
            const sampleFileDisplay = document.getElementById('sampleFileDisplay');
            const importBtn = document.getElementById('importXlsxBtn');
            const importForm = document.getElementById('xlsxImportForm');

            // Drag and drop functionality
            const dropZone = document.querySelector('label[for="xlsx_file"]');

            if (dropZone && xlsxFileInput) {
                setupDragAndDrop(dropZone, xlsxFileInput);
            }

            // File selection handler
            if (xlsxFileInput) {
                xlsxFileInput.addEventListener('change', function (e) {
                    const file = e.target.files[0];
                    if (file) {
                        handleFileSelection(file);
                    } else {
                        hideFileInfo();
                    }
                });
            }

            // Remove file button handler
            if (removeXlsxFileBtn) {
                removeXlsxFileBtn.addEventListener('click', function () {
                    removeSelectedFile();
                });
            }

            // Form submission handler
            if (importForm) {
                importForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    handleImportSubmission(e);
                });
            }

            // Modal reset on close
            const modal = document.getElementById('importAttendanceModal');
            if (modal) {
                observeModalClose(modal);
            }

            // Helper functions
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
                const maxSize = 10 * 1024 * 1024; // 10MB

                // Validate file size
                if (file.size > maxSize) {
                    showNotification('File size exceeds 10MB limit. Please choose a smaller file.', 'error');
                    xlsxFileInput.value = '';
                    hideFileInfo();
                    return;
                }

                // Validate file type
                const validTypes = ['.xlsx', '.xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
                if (!validTypes.includes(file.type) && !file.name.match(/\.(xlsx|xls)$/)) {
                    showNotification('Please select a valid Excel file (.xlsx or .xls)', 'error');
                    xlsxFileInput.value = '';
                    hideFileInfo();
                    return;
                }

                // Show file info
                showFileInfo(file);

                // Hide sample file display
                if (sampleFileDisplay) {
                    sampleFileDisplay.classList.add('hidden');
                }
            }

            function showFileInfo(file) {
                if (xlsxFileInfo && xlsxFileName && xlsxFileSize) {
                    xlsxFileName.textContent = file.name;

                    // Format file size
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

            function hideFileInfo() {
                if (xlsxFileInfo) {
                    xlsxFileInfo.classList.add('hidden');
                }
                if (xlsxFileName) xlsxFileName.textContent = '';
                if (xlsxFileSize) xlsxFileSize.textContent = '';
            }

            function removeSelectedFile() {
                if (xlsxFileInput) {
                    xlsxFileInput.value = '';
                    hideFileInfo();

                    // Show sample file display again
                    if (sampleFileDisplay) {
                        sampleFileDisplay.classList.remove('hidden');
                    }

                    showNotification('File removed successfully', 'info');
                }
            }

            function handleImportSubmission(e) {
                const fileInput = document.getElementById('xlsx_file');
                const hasFile = fileInput.files && fileInput.files[0];
                const sampleVisible = sampleFileDisplay && !sampleFileDisplay.classList.contains('hidden');

                // Check if a file is selected OR the sample file is visible
                if (!hasFile && !sampleVisible) {
                    showNotification('Please select an XLSX file to import', 'error');
                    return;
                }

                const progressContainer = document.getElementById('xlsxProgressContainer');
                const progressBar = document.getElementById('xlsxProgressBar');
                const progressPercent = document.getElementById('xlsxProgressPercent');
                const statusMessage = document.getElementById('xlsxStatusMessage');

                importBtn.disabled = true;
                progressContainer.classList.remove('hidden');
                progressBar.style.width = '0%';
                progressPercent.textContent = '0%';
                statusMessage.textContent = 'Uploading file...';

                // If using sample file, simulate import
                if (!hasFile && sampleVisible) {
                    simulateSampleImport(progressContainer, progressBar, progressPercent, statusMessage);
                    return;
                }

                // Regular file upload
                const formData = new FormData();
                formData.append('xlsx_file', fileInput.files[0]);

                // Simulate progress
                let progress = 0;
                const interval = setInterval(() => {
                    progress += 10;
                    if (progress <= 90) {
                        progressBar.style.width = progress + '%';
                        progressPercent.textContent = progress + '%';
                        if (progress === 30) {
                            statusMessage.textContent = 'Processing file...';
                        } else if (progress === 60) {
                            statusMessage.textContent = 'Importing records...';
                        }
                    }
                }, 300);

                fetch('import_attendance_xlsx.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        clearInterval(interval);
                        progressBar.style.width = '100%';
                        progressPercent.textContent = '100%';

                        setTimeout(() => {
                            progressContainer.classList.add('hidden');
                            importBtn.disabled = false;

                            if (data.success) {
                                showImportResults(data);
                                // Reset form
                                importForm.reset();
                                hideFileInfo();
                                if (sampleFileDisplay) {
                                    sampleFileDisplay.classList.remove('hidden');
                                }

                                // Reload page after successful import
                                setTimeout(() => {
                                    location.reload();
                                }, 3000);
                            } else {
                                showNotification(data.message || 'Import failed', 'error');
                            }
                        }, 500);
                    })
                    .catch(error => {
                        clearInterval(interval);
                        progressContainer.classList.add('hidden');
                        importBtn.disabled = false;
                        showNotification('Error importing file: ' + error.message, 'error');
                    });
            }

            function simulateSampleImport(progressContainer, progressBar, progressPercent, statusMessage) {
                let progress = 0;
                const interval = setInterval(() => {
                    progress += 20;
                    if (progress <= 100) {
                        progressBar.style.width = progress + '%';
                        progressPercent.textContent = progress + '%';

                        if (progress === 40) {
                            statusMessage.textContent = 'Processing sample file...';
                        } else if (progress === 60) {
                            statusMessage.textContent = 'Importing attendance records...';
                        } else if (progress === 80) {
                            statusMessage.textContent = 'Calculating OT and undertime...';
                        }
                    }

                    if (progress >= 100) {
                        clearInterval(interval);

                        setTimeout(() => {
                            progressContainer.classList.add('hidden');
                            importBtn.disabled = false;

                            // Show success message
                            const mockResult = {
                                success: true,
                                imported: 15,
                                duplicates: 2,
                                errors: 0,
                                message: 'Successfully imported 15 attendance records from JANUARY (1).xlsx',
                                records: [{
                                    employee: 'Jorel Vicente',
                                    date: '2024-01-15',
                                    total_hours: 8
                                },
                                {
                                    employee: 'Maylin Cajayon',
                                    date: '2024-01-15',
                                    total_hours: 8.5
                                },
                                {
                                    employee: 'Juan Dela Cruz',
                                    date: '2024-01-15',
                                    total_hours: 8
                                },
                                {
                                    employee: 'Maria Santos',
                                    date: '2024-01-15',
                                    total_hours: 8
                                },
                                {
                                    employee: 'Pedro Reyes',
                                    date: '2024-01-15',
                                    total_hours: 7.5
                                }
                                ]
                            };

                            showImportResults(mockResult);

                            // Hide sample file after import
                            if (sampleFileDisplay) {
                                sampleFileDisplay.classList.add('hidden');
                            }

                            // Reload page after successful import
                            setTimeout(() => {
                                location.reload();
                            }, 3000);
                        }, 500);
                    }
                }, 400);
            }

            function observeModalClose(modal) {
                const observer = new MutationObserver(function (mutations) {
                    mutations.forEach(function (mutation) {
                        if (mutation.attributeName === 'class' && modal.classList.contains('hidden')) {
                            // Modal closed - reset form
                            if (importForm) {
                                importForm.reset();
                            }
                            hideFileInfo();

                            // Show sample file display
                            if (sampleFileDisplay) {
                                sampleFileDisplay.classList.remove('hidden');
                            }

                            // Reset progress
                            const progressContainer = document.getElementById('xlsxProgressContainer');
                            if (progressContainer) {
                                progressContainer.classList.add('hidden');
                            }
                        }
                    });
                });

                observer.observe(modal, {
                    attributes: true
                });
            }
        }

        // Global function to remove sample file
        window.removeSampleFile = function () {
            const sampleFileDisplay = document.getElementById('sampleFileDisplay');
            const xlsxFileInput = document.getElementById('xlsx_file');

            if (sampleFileDisplay) {
                sampleFileDisplay.classList.add('hidden');
            }

            // Also clear any selected file
            if (xlsxFileInput) {
                xlsxFileInput.value = '';
                const fileInfo = document.getElementById('xlsxFileInfo');
                if (fileInfo) {
                    fileInfo.classList.add('hidden');
                }
            }

            showNotification('Sample file removed', 'info');
        };

        // ============================================
        // EXPORT ATTENDANCE FUNCTIONS - IMPROVED FOR GLOBAL SELECTION
        // ============================================

        const exportBtn = document.getElementById('exportBtn');

        if (exportBtn) {
            exportBtn.addEventListener('click', function (e) {
                e.preventDefault();
                openExportModal();
            });
        }

        // Global Select All button
        const globalSelectAllBtn = document.getElementById('globalSelectAllBtn');
        if (globalSelectAllBtn) {
            globalSelectAllBtn.addEventListener('click', function () {
                selectAllEmployeesGlobally();
            });
        }

        // Clear global selection
        document.getElementById('clearGlobalSelection')?.addEventListener('click', function () {
            clearGlobalSelection();
        });

        // Select All checkbox handler
        const selectAllCheckbox = document.getElementById('selectAllEmployees');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function () {
                // If using global selection, clear it first
                if (isGlobalSelection) {
                    clearGlobalSelection();
                }

                const checkboxes = document.querySelectorAll('.employee-checkbox:not(:disabled)');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });

                updateSelectedEmployeesFromCheckboxes();
            });
        }

        // Track checkbox changes
        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('employee-checkbox')) {
                // If using global selection, clear it
                if (isGlobalSelection) {
                    clearGlobalSelection();
                }
                updateSelectedEmployeesFromCheckboxes();
            }
        });

        // Function to select all employees with records across all pages
        function selectAllEmployeesGlobally() {
            // Show loading
            showNotification('Loading all employees...', 'info');

            // Get current filter values
            const search = document.getElementById('global_search')?.value || '';
            const department = document.getElementById('global_department')?.value || '';
            const status = document.getElementById('global_status')?.value || '';

            // Build URL
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
                        // Store global selection
                        globalSelectedEmployees = data.employees;
                        isGlobalSelection = true;

                        // Clear page-level selections
                        selectedEmployees = [];

                        // Update UI
                        updateGlobalSelectionUI();

                        // Uncheck all page-level checkboxes
                        document.querySelectorAll('.employee-checkbox').forEach(cb => {
                            cb.checked = false;
                        });
                        updateSelectAllCheckbox();

                        // Save to storage
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

        // Update global selection UI
        function updateGlobalSelectionUI() {
            const selectionInfo = document.getElementById('globalSelectionInfo');
            const selectedCountSpan = document.getElementById('globalSelectedCount');
            const selectedNamesSpan = document.getElementById('globalSelectedNames');
            const exportBtn = document.getElementById('exportBtn');

            console.log('Updating UI - Global:', globalSelectedEmployees.length, 'Page:', selectedEmployees.length, 'isGlobal:', isGlobalSelection);

            if (isGlobalSelection && globalSelectedEmployees.length > 0) {
                selectedCountSpan.textContent = globalSelectedEmployees.length;

                // Show first few names
                const names = globalSelectedEmployees.slice(0, 3).map(e => e.name).join(', ');
                const moreCount = globalSelectedEmployees.length > 3 ? ` and ${globalSelectedEmployees.length - 3} more` : '';
                selectedNamesSpan.textContent = names + moreCount;

                selectionInfo.classList.remove('hidden');

                // Update export button with count
                if (exportBtn) {
                    exportBtn.innerHTML = `<i class="fas fa-download mr-2"></i>Export (${globalSelectedEmployees.length} selected across all pages)`;
                }
            } else if (selectedEmployees.length > 0) {
                selectionInfo.classList.remove('hidden');
                selectedCountSpan.textContent = selectedEmployees.length;
                selectedNamesSpan.textContent = '';

                if (exportBtn) {
                    exportBtn.innerHTML = `<i class="fas fa-download mr-2"></i>Export (${selectedEmployees.length} selected)`;
                }
            } else {
                selectionInfo.classList.add('hidden');
                if (exportBtn) {
                    exportBtn.innerHTML = `<i class="fas fa-download mr-2"></i>Export`;
                }
            }
        }

        // Clear global selection
        function clearGlobalSelection() {
            // Clear both global and page-level selections
            globalSelectedEmployees = [];
            selectedEmployees = [];
            isGlobalSelection = false;

            // Uncheck all checkboxes
            document.querySelectorAll('.employee-checkbox').forEach(cb => {
                cb.checked = false;
            });

            // Update select all checkbox
            updateSelectAllCheckbox();

            // Clear from sessionStorage
            sessionStorage.removeItem('attendanceSelectedEmployees');
            sessionStorage.removeItem('attendanceGlobalSelected');
            sessionStorage.removeItem('attendanceIsGlobalSelection');

            // Update UI
            updateGlobalSelectionUI();

            showNotification('Selection cleared', 'info');
        }

        function openExportModal() {
            // Determine which employees to export
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

            // Reset modal
            document.getElementById('export_from_date').value = '';
            document.getElementById('export_to_date').value = '';
            document.getElementById('export_department').value = '';
            document.getElementById('format_excel').checked = true;
            document.getElementById('include_summary').checked = true;
            document.getElementById('include_employee_info').checked = true;
            document.getElementById('format_time_12h').checked = false;
            document.getElementById('exportSummary').classList.add('hidden');
            document.getElementById('exportError').classList.add('hidden');

            // Update selected count
            document.getElementById('selectedCount').textContent = employeesToExport.length;

            // Show/hide "across all pages" indicator
            const selectedFromAllPages = document.getElementById('selectedFromAllPages');
            if (isGlobal) {
                selectedFromAllPages.classList.remove('hidden');
            } else {
                selectedFromAllPages.classList.add('hidden');
            }

            // Show list of selected employees
            const selectedList = document.getElementById('selectedEmployeesList');
            if (employeesToExport.length <= 10) {
                let listHtml = '<div class="font-medium mb-1">Selected employees:</div>';
                listHtml += '<ul class="list-disc pl-4">';
                employeesToExport.forEach(emp => {
                    listHtml += `<li>${emp.name} (${emp.id})</li>`;
                });
                listHtml += '</ul>';
                selectedList.innerHTML = listHtml;
                selectedList.classList.remove('hidden');
            } else {
                selectedList.classList.add('hidden');
            }

            // Store employee IDs
            const employeeIds = employeesToExport.map(e => e.id).join(',');
            document.getElementById('exportEmployeeIds').value = employeeIds;
            document.getElementById('exportIsGlobal').value = isGlobal ? '1' : '0';

            // Show modal
            const modal = document.getElementById('exportAttendanceModal');
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');

            // Check if there are records
            checkExportRecords();
        }

        function closeExportModal() {
            const modal = document.getElementById('exportAttendanceModal');
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
        }

        function checkExportRecords() {
            const fromDate = document.getElementById('export_from_date').value;
            const toDate = document.getElementById('export_to_date').value;
            const department = document.getElementById('export_department').value;
            const employeeIds = document.getElementById('exportEmployeeIds').value;

            if (!employeeIds) return;

            // Build URL
            let url = `check_export_records.php?employee_ids=${encodeURIComponent(employeeIds)}`;
            if (fromDate) url += `&from_date=${encodeURIComponent(fromDate)}`;
            if (toDate) url += `&to_date=${encodeURIComponent(toDate)}`;
            if (department) url += `&department=${encodeURIComponent(department)}`;

            // Show loading
            document.getElementById('exportLoading').classList.remove('hidden');
            document.getElementById('exportError').classList.add('hidden');

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('exportLoading').classList.add('hidden');

                    if (data.has_records) {
                        // Show summary
                        const employeeCount = data.employee_count || globalSelectedEmployees.length || selectedEmployees.length;
                        let summaryText = `${employeeCount} employee(s) selected`;
                        if (fromDate && toDate) {
                            summaryText += ` for period ${formatDateDisplay(fromDate)} to ${formatDateDisplay(toDate)}`;
                        } else if (fromDate) {
                            summaryText += ` from ${formatDateDisplay(fromDate)} onwards`;
                        } else if (toDate) {
                            summaryText += ` up to ${formatDateDisplay(toDate)}`;
                        }
                        if (department) {
                            summaryText += ` in ${department} department`;
                        }
                        summaryText += `. Found ${data.record_count || 0} records. Ready to export.`;

                        document.getElementById('exportSummaryText').textContent = summaryText;
                        document.getElementById('exportSummary').classList.remove('hidden');
                        document.getElementById('proceedExportBtn').disabled = false;
                    } else {
                        // Show error
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

            // Build URL
            let url = `export_multiple_attendance.php?employee_ids=${encodeURIComponent(employeeIds)}&format=${format}`;
            if (fromDate) url += `&from_date=${encodeURIComponent(fromDate)}`;
            if (toDate) url += `&to_date=${encodeURIComponent(toDate)}`;
            if (department) url += `&department=${encodeURIComponent(department)}`;
            url += `&include_summary=${includeSummary}`;
            url += `&include_employee_info=${includeEmployeeInfo}`;
            url += `&format_time_12h=${formatTime12h}`;

            // Show loading
            const originalText = document.getElementById('proceedExportBtn').innerHTML;
            document.getElementById('proceedExportBtn').innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Exporting...';
            document.getElementById('proceedExportBtn').disabled = true;

            // Create and click a hidden anchor tag
            const link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Restore button and close modal after delay
            setTimeout(() => {
                document.getElementById('proceedExportBtn').innerHTML = originalText;
                document.getElementById('proceedExportBtn').disabled = false;
                closeExportModal();
                showNotification('Export started! Your download will begin shortly.', 'success');
            }, 1500);
        }

        // Add event listeners for date/department changes
        document.getElementById('export_from_date')?.addEventListener('change', checkExportRecords);
        document.getElementById('export_to_date')?.addEventListener('change', checkExportRecords);
        document.getElementById('export_department')?.addEventListener('change', checkExportRecords);

        // ============================================
        // GLOBAL SEARCH AND FILTER FUNCTIONS
        // ============================================

        const globalSearch = document.getElementById('global_search');
        const globalDepartment = document.getElementById('global_department');
        const globalStatus = document.getElementById('global_status');

        function performSearch() {
            // Clear previous timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }

            // Show loading spinner
            document.getElementById('loadingSpinner').classList.remove('hidden');
            document.getElementById('employeeTableContainer').classList.add('opacity-50');

            // Get filter values
            const search = globalSearch ? globalSearch.value : '';
            const department = globalDepartment ? globalDepartment.value : '';
            const status = globalStatus ? globalStatus.value : '';

            // Build URL with search params
            const params = new URLSearchParams({
                ajax_search: true,
                search: search,
                department: department,
                status_filter: status,
                page: currentPage,
                per_page: recordsPerPage
            });

            // Make AJAX request
            fetch(`attendance.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    // Update table with results (this will preserve selections because we're not clearing selectedEmployees)
                    updateEmployeeTable(data.employees);

                    // Update pagination info
                    totalPages = data.pages;
                    document.getElementById('totalRecords').textContent = data.total;
                    document.getElementById('totalPages').textContent = data.pages;

                    // Update showing info
                    const from = data.total > 0 ? ((currentPage - 1) * recordsPerPage) + 1 : 0;
                    const to = data.total > 0 ? Math.min(currentPage * recordsPerPage, data.total) : 0;
                    document.getElementById('showingFrom').textContent = from;
                    document.getElementById('showingTo').textContent = to;

                    // Update pagination UI
                    updatePaginationUI();

                    // Hide loading spinner
                    document.getElementById('loadingSpinner').classList.add('hidden');
                    document.getElementById('employeeTableContainer').classList.remove('opacity-50');

                    // Update URL without reloading
                    const url = new URL(window.location.href);
                    if (search) url.searchParams.set('search', search);
                    else url.searchParams.delete('search');
                    if (department) url.searchParams.set('department', department);
                    else url.searchParams.delete('department');
                    if (status) url.searchParams.set('status_filter', status);
                    else url.searchParams.delete('status_filter');
                    url.searchParams.set('page', currentPage);
                    url.searchParams.set('per_page', recordsPerPage);
                    window.history.pushState({}, '', url.toString());

                    // Update global selection UI (don't clear selections)
                    updateGlobalSelectionUI();
                })
                .catch(error => {
                    console.error('Search error:', error);
                    document.getElementById('loadingSpinner').classList.add('hidden');
                    document.getElementById('employeeTableContainer').classList.remove('opacity-50');
                    showNotification('Error performing search. Please try again.', 'error');
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

                // Check if this employee is selected
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
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">${escapeHtml(emp.full_name)}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-700">${escapeHtml(emp.department)}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full ${typeClass}">
                            ${escapeHtml(emp.type)}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        ${emp.total_records > 0 ? `<span class="font-semibold text-gray-900">${emp.total_records}</span>` : '<span class="text-gray-400">0</span>'}
                    </td>
                    <td class="px-4 py-3">
                        ${emp.present_days > 0 ? `<span class="font-semibold text-green-600">${emp.present_days}</span>` : '<span class="text-gray-400">0</span>'}
                    </td>
                    <td class="px-4 py-3">
                        ${emp.total_hours > 0 ? `
                            <span class="font-semibold text-blue-600">${Math.round(emp.total_hours * 10) / 10}h</span>
                            ${emp.total_ot > 0 ? `<span class="text-xs text-orange-600 ml-1">(OT: ${Math.round(emp.total_ot * 10) / 10}h)</span>` : ''}
                        ` : '<span class="text-gray-400">0h</span>'}
                    </td>
                    <td class="px-4 py-3 text-gray-700">${lastDate}</td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex space-x-2 justify-center">
                            ${actionButton}
                        </div>
                    </td>
                </tr>
                `;
            });

            tbody.innerHTML = html;

            // Re-attach event listeners for checkboxes (they'll be handled by event delegation)
            updateSelectAllCheckbox();
        }

        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Debounced search on input
        if (globalSearch) {
            globalSearch.addEventListener('input', function () {
                currentPage = 1; // Reset to first page on new search
                if (searchTimeout) clearTimeout(searchTimeout);
                searchTimeout = setTimeout(performSearch, 300);
            });
        }

        // Filter changes
        if (globalDepartment) {
            globalDepartment.addEventListener('change', function () {
                currentPage = 1;
                performSearch();
            });
        }

        if (globalStatus) {
            globalStatus.addEventListener('change', function () {
                currentPage = 1;
                performSearch();
            });
        }

        // Pagination functions
        // ============================================
        // PAGINATION FUNCTIONS FOR EMPLOYEE SUMMARY
        // ============================================

        window.changePage = function (page) {
            if (page < 1 || page > totalPages) return;

            // Get current filter values
            const search = document.getElementById('global_search')?.value || '';
            const department = document.getElementById('global_department')?.value || '';
            const status = document.getElementById('global_status')?.value || '';

            // Update URL with page parameter
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            url.searchParams.set('per_page', recordsPerPage);

            if (search) url.searchParams.set('search', search);
            else url.searchParams.delete('search');

            if (department) url.searchParams.set('department', department);
            else url.searchParams.delete('department');

            if (status) url.searchParams.set('status_filter', status);
            else url.searchParams.delete('status_filter');

            // Navigate to new page
            window.location.href = url.toString();
        };

        window.changePerPage = function (perPage) {
            recordsPerPage = parseInt(perPage);

            // Update URL with new per_page value and reset to page 1
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', recordsPerPage);
            url.searchParams.set('page', 1);

            // Preserve filters
            const search = document.getElementById('global_search')?.value;
            const department = document.getElementById('global_department')?.value;
            const status = document.getElementById('global_status')?.value;

            if (search) url.searchParams.set('search', search);
            else url.searchParams.delete('search');

            if (department) url.searchParams.set('department', department);
            else url.searchParams.delete('department');

            if (status) url.searchParams.set('status_filter', status);
            else url.searchParams.delete('status_filter');

            // Navigate to new page
            window.location.href = url.toString();
        };

        function updatePaginationUI() {
            const paginationNav = document.getElementById('paginationNav');
            if (!paginationNav) return;

            if (totalPages <= 1) {
                paginationNav.innerHTML = '';
                return;
            }

            let html = `
                <button onclick="changePage(1)" ${currentPage <= 1 ? 'disabled' : ''} class="pagination-btn" title="First Page">
                    <i class="fas fa-angle-double-left"></i>
                </button>
                <button onclick="changePage(${currentPage - 1})" ${currentPage <= 1 ? 'disabled' : ''} class="pagination-btn" title="Previous Page">
                    <i class="fas fa-angle-left"></i>
                </button>
            `;

            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, currentPage + 2);

            if (startPage > 1) {
                html += `<button onclick="changePage(1)" class="pagination-btn">1</button>`;
                if (startPage > 2) {
                    html += `<span class="pagination-ellipsis">...</span>`;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === currentPage ? 'active' : '';
                html += `<button onclick="changePage(${i})" class="pagination-btn ${activeClass}">${i}</button>`;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<span class="pagination-ellipsis">...</span>`;
                }
                html += `<button onclick="changePage(${totalPages})" class="pagination-btn">${totalPages}</button>`;
            }

            html += `
                <button onclick="changePage(${currentPage + 1})" ${currentPage >= totalPages ? 'disabled' : ''} class="pagination-btn" title="Next Page">
                    <i class="fas fa-angle-right"></i>
                </button>
                <button onclick="changePage(${totalPages})" ${currentPage >= totalPages ? 'disabled' : ''} class="pagination-btn" title="Last Page">
                    <i class="fas fa-angle-double-right"></i>
                </button>
            `;

            paginationNav.innerHTML = html;
        }

        // Filter clear functions
        window.clearSearch = function () {
            if (globalSearch) {
                globalSearch.value = '';
                currentPage = 1;
                performSearch();
            }
        };

        window.clearDepartment = function () {
            if (globalDepartment) {
                globalDepartment.value = '';
                currentPage = 1;
                performSearch();
            }
        };

        window.clearStatus = function () {
            if (globalStatus) {
                globalStatus.value = '';
                currentPage = 1;
                performSearch();
            }
        };

        window.clearAllFilters = function () {
            if (globalSearch) globalSearch.value = '';
            if (globalDepartment) globalDepartment.value = '';
            if (globalStatus) globalStatus.value = '';
            currentPage = 1;
            performSearch();
        };

        // ============================================
        // EDIT ATTENDANCE FUNCTION
        // ============================================

        window.editAttendance = function (attendanceId) {
            const modal = document.getElementById('editAttendanceModal');

            if (!modal) {
                console.error('Edit modal not found');
                return;
            }

            try {
                const modalInstance = new Modal(modal);
                modalInstance.show();
            } catch (e) {
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
            }

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
                    const editFormContent = document.getElementById('editFormContent');

                    if (data.success) {
                        const record = data.record;
                        const dateValue = record.date;
                        const amInValue = record.am_time_in || '';
                        const amOutValue = record.am_time_out || '';
                        const pmInValue = record.pm_time_in || '';
                        const pmOutValue = record.pm_time_out || '';

                        let redirectParams = '<?php echo $current_view_params; ?>';
                        if (redirectParams) {
                            redirectParams += '&status=edit_success';
                        } else {
                            redirectParams = '?status=edit_success';
                        }

                        const formHTML = `
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
                                    <input type="date" name="date" value="${dateValue}" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                                </div>
                            </div>
                            
                            <div class="border-t pt-4 mb-4">
                                <h6 class="text-lg font-semibold text-blue-600 mb-3">Morning Shift (AM)</h6>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-900">Time-in</label>
                                        <input type="time" name="am_time_in" value="${amInValue}" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                    </div>
                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-900">Time-out</label>
                                        <input type="time" name="am_time_out" value="${amOutValue}" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="border-t pt-4 mb-4">
                                <h6 class="text-lg font-semibold text-blue-600 mb-3">Afternoon Shift (PM)</h6>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-900">Time-in</label>
                                        <input type="time" name="pm_time_in" value="${pmInValue}" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                    </div>
                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-900">Time-out</label>
                                        <input type="time" name="pm_time_out" value="${pmOutValue}" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-end space-x-3 pt-4 border-t">
                                <button type="submit" class="text-white bg-green-600 hover:bg-green-700 font-medium rounded-lg text-sm px-5 py-2.5 text-center flex items-center">
                                    <i class="fas fa-save mr-2"></i>Update Record
                                </button>
                                <button type="button" class="text-gray-500 bg-white hover:bg-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 border border-gray-300 close-edit-modal" onclick="closeEditModal()">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    `;

                        editFormContent.innerHTML = formHTML;
                    } else {
                        editFormContent.innerHTML = `
                        <div class="text-center py-8 text-red-600">
                            <i class="fas fa-exclamation-triangle text-3xl mb-3"></i>
                            <p>Error loading record: ${data.message || 'Record not found'}</p>
                            <button type="button" class="mt-4 text-gray-500 bg-white hover:bg-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 border border-gray-300 close-edit-modal" onclick="closeEditModal()">
                                Close
                            </button>
                        </div>
                    `;
                    }
                })
                .catch(error => {
                    const editFormContent = document.getElementById('editFormContent');
                    editFormContent.innerHTML = `
                    <div class="text-center py-8 text-red-600">
                        <i class="fas fa-exclamation-triangle text-3xl mb-3"></i>
                        <p>Network error. Please try again.</p>
                        <button type="button" class="mt-4 text-gray-500 bg-white hover:bg-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 border border-gray-300 close-edit-modal" onclick="closeEditModal()">
                            Close
                        </button>
                    </div>
                `;
                });
        };

        // ============================================
        // CLOSE EDIT MODAL FUNCTION
        // ============================================

        window.closeEditModal = function () {
            const modal = document.getElementById('editAttendanceModal');
            if (modal) {
                try {
                    const modalInstance = new Modal(modal);
                    modalInstance.hide();
                } catch (e) {
                    modal.classList.add('hidden');
                    modal.setAttribute('aria-hidden', 'true');
                }
            }
        };

        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('close-edit-modal') ||
                e.target.closest('.close-edit-modal') ||
                (e.target.closest('button') && e.target.closest('button').hasAttribute('data-modal-hide') && e.target.closest('button').getAttribute('data-modal-hide') === 'editAttendanceModal')) {
                closeEditModal();
            }
        });

        // ============================================
        // DELETE ATTENDANCE FUNCTION
        // ============================================

        window.deleteAttendance = function (attendanceId, employeeName, date) {
            if (confirm(`Are you sure you want to delete the attendance record for ${employeeName} on ${date}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?php echo $_SERVER['PHP_SELF'] . $current_view_params; ?>';

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
                    input.addEventListener('change', function () {
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
        // SHOW NOTIFICATION FUNCTION
        // ============================================

        window.showNotification = function (message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-white ${type === 'success' ? 'bg-green-500' :
                type === 'error' ? 'bg-red-500' :
                    'bg-blue-500'
                } transform transition-all duration-300 translate-y-0`;

            let icon = 'fa-check-circle';
            if (type === 'error') icon = 'fa-exclamation-circle';
            if (type === 'info') icon = 'fa-info-circle';

            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${icon} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.transform = 'translateY(-100px)';
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        };

        // ============================================
        // SHOW IMPORT RESULTS FUNCTION
        // ============================================

        window.showImportResults = function (data) {
            const modal = document.getElementById('importResultsModal');
            const header = document.getElementById('importResultsHeader');
            const content = document.getElementById('importResultsContent');

            if (!modal || !header || !content) {
                alert(`Import Results:\n\nSuccess: ${data.success}\nImported: ${data.imported || 0}\nDuplicates: ${data.duplicates || 0}\n${data.message || ''}`);
                return;
            }

            header.className = `flex items-center justify-between p-5 border-b rounded-t ${data.success ? 'bg-green-600' : 'bg-red-600'
                } text-white`;

            let html = `
                <div class="text-center mb-4">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full ${data.success ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'
                } mb-4">
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

            if (data.records && data.records.length > 0) {
                html += `
                    <div class="mt-4">
                        <h5 class="text-sm font-semibold text-gray-700 mb-2">Recently Imported:</h5>
                        <div class="max-h-40 overflow-y-auto bg-gray-50 p-3 rounded-lg">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-gray-600">
                                        <th class="text-left pb-2">Employee</th>
                                        <th class="text-left pb-2">Date</th>
                                        <th class="text-left pb-2">Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.records.slice(0, 5).map(rec => `
                                        <tr class="border-t border-gray-200">
                                            <td class="py-1">${rec.employee || ''}</td>
                                            <td class="py-1">${rec.date || ''}</td>
                                            <td class="py-1 text-green-600">${rec.total_hours || 0}h</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                            ${data.records.length > 5 ? `<p class="text-xs text-gray-500 mt-2">... and ${data.records.length - 5} more records</p>` : ''}
                        </div>
                    </div>
                `;
            }

            content.innerHTML = html;

            try {
                const modalInstance = new Modal(modal);
                modalInstance.show();
            } catch (e) {
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
            }
        };

        // ============================================
        // KEYBOARD NAVIGATION
        // ============================================

        document.addEventListener('DOMContentLoaded', function () {
            // Keyboard navigation for pagination
            document.addEventListener('keydown', function (e) {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                    return; // Don't interfere with form inputs
                }

                if (e.key === 'ArrowLeft' && currentPage > 1) {
                    changePage(currentPage - 1);
                } else if (e.key === 'ArrowRight' && currentPage < totalPages) {
                    changePage(currentPage + 1);
                }
            });

            // Auto-scroll to top when paginating
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('page')) {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
        });
    </script>
    <script>
        // ============================================
        // MONTH AND YEAR FILTER FUNCTIONS
        // ============================================

        function applyMonthYearFilter() {
            const month = document.getElementById('monthFilter').value;
            const year = document.getElementById('yearFilter').value;

            // Get current URL
            const url = new URL(window.location.href);

            // Update or remove month parameter
            if (month) {
                url.searchParams.set('month', month);
            } else {
                url.searchParams.delete('month');
            }

            // Update or remove year parameter
            if (year) {
                url.searchParams.set('year', year);
            } else {
                url.searchParams.delete('year');
            }

            // Make sure we keep view_attendance and employee_id
            if (!url.searchParams.has('view_attendance')) {
                url.searchParams.set('view_attendance', 'true');
            }

            // Get employee_id from current URL or from the page
            let employeeId = url.searchParams.get('employee_id');
            if (!employeeId) {
                // Try to get from the page if not in URL
                const urlParams = new URLSearchParams(window.location.search);
                employeeId = urlParams.get('employee_id');
                if (employeeId) {
                    url.searchParams.set('employee_id', employeeId);
                }
            }

            console.log('Applying filter with:', { month, year, employeeId });

            // Reload with new filters
            window.location.href = url.toString();
        }

        function clearMonthYearFilter() {
            const url = new URL(window.location.href);

            // Remove month and year parameters
            url.searchParams.delete('month');
            url.searchParams.delete('year');

            // Make sure we keep view_attendance and employee_id
            if (!url.searchParams.has('view_attendance')) {
                url.searchParams.set('view_attendance', 'true');
            }

            // Get employee_id from current URL or from the page
            let employeeId = url.searchParams.get('employee_id');
            if (!employeeId) {
                const urlParams = new URLSearchParams(window.location.search);
                employeeId = urlParams.get('employee_id');
                if (employeeId) {
                    url.searchParams.set('employee_id', employeeId);
                }
            }

            console.log('Clearing filters');

            window.location.href = url.toString();
        }

        // Add event listeners for Enter key
        document.addEventListener('DOMContentLoaded', function () {
            const monthFilter = document.getElementById('monthFilter');
            const yearFilter = document.getElementById('yearFilter');

            if (monthFilter) {
                monthFilter.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        applyMonthYearFilter();
                    }
                });
            }

            if (yearFilter) {
                yearFilter.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        applyMonthYearFilter();
                    }
                });
            }
        });

        // Optional: Add this for debugging
        console.log('Current URL params:', new URLSearchParams(window.location.search).toString());
    </script>
    <script>
        // ============================================
        // ATTENDANCE PAGINATION FUNCTIONS
        // ============================================

        function changeAttendancePage(page) {
            const url = new URL(window.location.href);

            // Ensure page is at least 1 and an integer
            page = Math.max(1, parseInt(page) || 1);

            url.searchParams.set('att_page', page);

            // Preserve existing filters
            const month = document.getElementById('monthFilter')?.value;
            const year = document.getElementById('yearFilter')?.value;

            if (month) {
                url.searchParams.set('month', month);
            } else {
                url.searchParams.delete('month');
            }

            if (year) {
                url.searchParams.set('year', year);
            } else {
                url.searchParams.delete('year');
            }

            // Preserve per_page setting
            const perPage = document.getElementById('attPerPage')?.value;
            if (perPage) {
                url.searchParams.set('per_page', perPage);
            }

            // Make sure we keep view_attendance and employee_id
            url.searchParams.set('view_attendance', 'true');

            window.location.href = url.toString();
        }

        function changeAttendancePerPage(perPage) {
    const url = new URL(window.location.href);
    
    // Validate perPage
    perPage = parseInt(perPage) || 10; // Changed from 20 to 10
    const validValues = [10, 20, 50, 100];
    if (!validValues.includes(perPage)) {
        perPage = 10; // Changed from 20 to 10
    }
    
    url.searchParams.set('per_page', perPage);
    url.searchParams.set('att_page', '1'); // Reset to first page
    
    // Preserve existing filters
    const month = document.getElementById('monthFilter')?.value;
    const year = document.getElementById('yearFilter')?.value;
    
    if (month) {
        url.searchParams.set('month', month);
    } else {
        url.searchParams.delete('month');
    }
    
    if (year) {
        url.searchParams.set('year', year);
    } else {
        url.searchParams.delete('year');
    }
    
    // Make sure we keep view_attendance and employee_id
    url.searchParams.set('view_attendance', 'true');
    
    window.location.href = url.toString();
}
    </script>
</body>

</html>
<?php
// Close PDO connection
$pdo = null;
?>