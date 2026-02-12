<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Logout functionality
if (isset($_GET['logout'])) {
    // Destroy all session data
    session_destroy();

    // Clear remember me cookie
    setcookie('remember_user', '', time() - 3600, "/");

    // Redirect to login page
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

// Define database credentials
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
$current_view_params = ''; // Track current view for redirects

// =================================================================================
// --- PDO Database Connection ---
// =================================================================================

try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("ERROR: Could not connect to the database. Check server logs for details.");
}

// =================================================================================
// --- NEW: Delete Attendance Record ---
// =================================================================================

if (isset($_POST['delete_attendance_id'])) {
    $attendance_id = filter_input(INPUT_POST, 'delete_attendance_id', FILTER_VALIDATE_INT);

    if ($attendance_id) {
        try {
            // Get record details for confirmation message
            $get_sql = "SELECT employee_name, date, employee_id FROM attendance WHERE id = ?";
            $get_stmt = $pdo->prepare($get_sql);
            $get_stmt->execute([$attendance_id]);
            $record = $get_stmt->fetch(PDO::FETCH_ASSOC);

            if ($record) {
                // Delete the record
                $delete_sql = "DELETE FROM attendance WHERE id = ?";
                $delete_stmt = $pdo->prepare($delete_sql);

                if ($delete_stmt->execute([$attendance_id])) {
                    $success_message = "Attendance record for " . htmlspecialchars($record['employee_name']) .
                        " on " . date('M d, Y', strtotime($record['date'])) . " deleted successfully.";

                    // If we're viewing a specific employee's attendance, stay on that view
                    if (isset($_GET['view_attendance']) && $_GET['view_attendance'] == 'true' && isset($_GET['employee_id'])) {
                        // Stay on the same employee view
                        $view_employee_id = $_GET['employee_id'];
                        // Re-fetch attendance records for this employee
                        $view_sql = "SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 100";
                        $view_stmt = $pdo->prepare($view_sql);
                        $view_stmt->execute([$view_employee_id]);
                        $view_attendance_records = $view_stmt->fetchAll(PDO::FETCH_ASSOC);
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
// --- NEW: Handle View Attendance Request ---
// =================================================================================

if (isset($_GET['view_attendance']) && isset($_GET['employee_id'])) {
    $view_employee_id = filter_input(INPUT_GET, 'employee_id', FILTER_SANITIZE_STRING);

    // Get employee details
    $employee_details = getEmployeeById($pdo, $view_employee_id);

    if ($employee_details) {
        // Get attendance records for this employee
        try {
            $view_sql = "SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 100";
            $view_stmt = $pdo->prepare($view_sql);
            $view_stmt->execute([$view_employee_id]);
            $view_attendance_records = $view_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("View attendance error: " . $e->getMessage());
            $error_message = "Could not retrieve attendance records for this employee.";
        }
    } else {
        $error_message = "Employee not found.";
    }
}

// =================================================================================
// --- Helper Function: Calculate Working Hours ---
// =================================================================================

function calculateWorkingHours($am_in, $am_out, $pm_in, $pm_out)
{
    $total_minutes = 0;
    $ot_minutes = 0;
    $undertime_minutes = 0;

    // Standard working hours: 8:00 AM - 5:00 PM with 1-hour break (8 hours total)
    $standard_start_am = strtotime('08:00:00');
    $standard_end_am = strtotime('12:00:00');
    $standard_start_pm = strtotime('13:00:00');
    $standard_end_pm = strtotime('17:00:00');

    // Calculate AM hours
    if ($am_in && $am_out) {
        $am_in_time = strtotime($am_in);
        $am_out_time = strtotime($am_out);

        if ($am_in_time && $am_out_time && $am_out_time > $am_in_time) {
            $am_worked = ($am_out_time - $am_in_time) / 60; // Convert to minutes

            // Check for early arrival (OT)
            if ($am_in_time < $standard_start_am) {
                $ot_minutes += ($standard_start_am - $am_in_time) / 60;
            }

            // Check for late arrival (Undertime)
            if ($am_in_time > $standard_start_am) {
                $undertime_minutes += ($am_in_time - $standard_start_am) / 60;
            }

            $total_minutes += $am_worked;
        }
    }

    // Calculate PM hours
    if ($pm_in && $pm_out) {
        $pm_in_time = strtotime($pm_in);
        $pm_out_time = strtotime($pm_out);

        if ($pm_in_time && $pm_out_time && $pm_out_time > $pm_in_time) {
            $pm_worked = ($pm_out_time - $pm_in_time) / 60;

            // Check for late departure (OT)
            if ($pm_out_time > $standard_end_pm) {
                $ot_minutes += ($pm_out_time - $standard_end_pm) / 60;
            }

            // Check for early departure (Undertime)
            if ($pm_out_time < $standard_end_pm) {
                $undertime_minutes += ($standard_end_pm - $pm_out_time) / 60;
            }

            $total_minutes += $pm_worked;
        }
    }

    // Convert minutes to hours (rounded to 2 decimal places)
    $total_hours = round($total_minutes / 60, 2);
    $ot_hours = round($ot_minutes / 60, 2);
    $undertime_hours = round($undertime_minutes / 60, 2);

    return [
        'total_hours' => max(0, $total_hours),
        'ot_hours' => max(0, $ot_hours),
        'undertime_hours' => max(0, $undertime_hours)
    ];
}

// =================================================================================
// --- NEW: Get Employee Types from Database ---
// =================================================================================

function getEmployeeTypes($pdo)
{
    $employees = [];

    // Get permanent employees
    $permanent_sql = "SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name, office as department FROM permanent WHERE status = 'Active'";
    $stmt = $pdo->query($permanent_sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $employees[] = [
            'employee_id' => $row['employee_id'],
            'full_name' => $row['full_name'],
            'department' => $row['department'],
            'type' => 'Permanent'
        ];
    }

    // Get job order employees
    $joborder_sql = "SELECT employee_id, employee_name as full_name, office as department FROM job_order WHERE is_archived = 0";
    $stmt = $pdo->query($joborder_sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $employees[] = [
            'employee_id' => $row['employee_id'],
            'full_name' => $row['full_name'],
            'department' => $row['department'],
            'type' => 'Job Order'
        ];
    }

    // Get contractual employees
    $contractual_sql = "SELECT employee_id, full_name, office_assignment as department FROM contractofservice WHERE status = 'active'";
    $stmt = $pdo->query($contractual_sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $employees[] = [
            'employee_id' => $row['employee_id'],
            'full_name' => $row['full_name'],
            'department' => $row['department'],
            'type' => 'Contractual'
        ];
    }

    return $employees;
}

// =================================================================================
// --- NEW: Get Employee by ID ---
// =================================================================================

function getEmployeeById($pdo, $employee_id)
{
    // Search in permanent table
    $permanent_sql = "SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name, office as department FROM permanent WHERE employee_id = ? AND status = 'Active'";
    $stmt = $pdo->prepare($permanent_sql);
    $stmt->execute([$employee_id]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        return [
            'employee_id' => $row['employee_id'],
            'full_name' => $row['full_name'],
            'department' => $row['department'],
            'type' => 'Permanent'
        ];
    }

    // Search in job order table
    $joborder_sql = "SELECT employee_id, employee_name as full_name, office as department FROM job_order WHERE employee_id = ? AND is_archived = 0";
    $stmt = $pdo->prepare($joborder_sql);
    $stmt->execute([$employee_id]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        return [
            'employee_id' => $row['employee_id'],
            'full_name' => $row['full_name'],
            'department' => $row['department'],
            'type' => 'Job Order'
        ];
    }

    // Search in contractual table
    $contractual_sql = "SELECT employee_id, full_name, office_assignment as department FROM contractofservice WHERE employee_id = ? AND status = 'active'";
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
// --- NEW: Get Employee Summary (One row per employee) ---
// =================================================================================

function getEmployeeSummary($pdo)
{
    $employees = [];

    // Get distinct employees from attendance records
    $sql = "SELECT DISTINCT employee_id, employee_name, department 
            FROM attendance 
            ORDER BY employee_name ASC";

    try {
        $stmt = $pdo->query($sql);
        $attendance_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Also get all active employees for comparison
        $all_employees = getEmployeeTypes($pdo);

        // Combine both lists
        $combined = [];

        // Add employees with attendance records
        foreach ($attendance_employees as $emp) {
            $key = $emp['employee_id'];
            $combined[$key] = [
                'employee_id' => $emp['employee_id'],
                'full_name' => $emp['employee_name'],
                'department' => $emp['department'],
                'has_attendance' => true
            ];
        }

        // Add employees without attendance records
        foreach ($all_employees as $emp) {
            $key = $emp['employee_id'];
            if (!isset($combined[$key])) {
                $combined[$key] = [
                    'employee_id' => $emp['employee_id'],
                    'full_name' => $emp['full_name'],
                    'department' => $emp['department'],
                    'has_attendance' => false,
                    'type' => $emp['type']
                ];
            } else {
                // Add type if missing
                $combined[$key]['type'] = $emp['type'];
            }
        }

        return array_values($combined);
    } catch (PDOException $e) {
        error_log("Get employee summary error: " . $e->getMessage());
        return [];
    }
}

// =================================================================================
// --- NEW: Generate Dates for 2 Weeks ---
// =================================================================================

function generateTwoWeeksDates($start_date = null)
{
    if (!$start_date) {
        // Default to current date or 1st/16th of month
        $today = date('d');
        if ($today <= 15) {
            $start_date = date('Y-m-01');
        } else {
            $start_date = date('Y-m-16');
        }
    }

    $dates = [];
    $current_date = new DateTime($start_date);

    // Generate 14 days (2 weeks)
    for ($i = 0; $i < 14; $i++) {
        $dates[] = $current_date->format('Y-m-d');
        $current_date->modify('+1 day');
    }

    return $dates;
}

// =================================================================================
// --- NEW: Check if we should show employee summary view ---
// =================================================================================

if (isset($_GET['view']) && $_GET['view'] == 'employees') {
    $show_employee_summary = true;
    $employee_summary = getEmployeeSummary($pdo);
}

// =================================================================================
// --- NEW: Bulk Add Attendance with Daily Times ---
// =================================================================================

if (isset($_POST['bulk_add_attendance'])) {
    $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_SANITIZE_STRING);
    $employee_name = filter_input(INPUT_POST, 'employee_name', FILTER_SANITIZE_STRING);
    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
    $start_date = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
    $end_date = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);

    // Validate required fields
    if (empty($employee_id) || empty($employee_name) || empty($department) || empty($start_date) || empty($end_date)) {
        $error_message = "Error: Please fill all required fields.";
    } else {
        $success_count = 0;
        $error_count = 0;
        $dates_added = [];

        // Calculate number of days between dates
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = $start->diff($end);
        $days_count = $interval->days + 1;

        if ($days_count > 31) {
            $error_message = "Error: Maximum period is 31 days.";
        } else {
            $current_date = clone $start;

            try {
                // Begin transaction
                $pdo->beginTransaction();

                for ($i = 0; $i < $days_count; $i++) {
                    $date = $current_date->format('Y-m-d');
                    $date_key = $current_date->format('Y-m-d');

                    // Get times for this specific day
                    $am_time_in = isset($_POST['am_time_in'][$date_key]) && !empty($_POST['am_time_in'][$date_key]) ? $_POST['am_time_in'][$date_key] : null;
                    $am_time_out = isset($_POST['am_time_out'][$date_key]) && !empty($_POST['am_time_out'][$date_key]) ? $_POST['am_time_out'][$date_key] : null;
                    $pm_time_in = isset($_POST['pm_time_in'][$date_key]) && !empty($_POST['pm_time_in'][$date_key]) ? $_POST['pm_time_in'][$date_key] : null;
                    $pm_time_out = isset($_POST['pm_time_out'][$date_key]) && !empty($_POST['pm_time_out'][$date_key]) ? $_POST['pm_time_out'][$date_key] : null;

                    // Skip if all times are empty (holiday/leave)
                    if (empty($am_time_in) && empty($am_time_out) && empty($pm_time_in) && empty($pm_time_out)) {
                        $current_date->modify('+1 day');
                        continue;
                    }

                    // Check if attendance already exists
                    $check_sql = "SELECT id FROM attendance WHERE employee_id = ? AND date = ?";
                    $check_stmt = $pdo->prepare($check_sql);
                    $check_stmt->execute([$employee_id, $date]);

                    if ($check_stmt->rowCount() == 0) {
                        // Calculate working hours
                        $hours = calculateWorkingHours($am_time_in, $am_time_out, $pm_time_in, $pm_time_out);

                        // Insert attendance record
                        $sql = "INSERT INTO attendance 
                                (date, employee_id, employee_name, department, am_time_in, am_time_out, pm_time_in, pm_time_out, ot_hours, under_time, total_hours) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                        $stmt = $pdo->prepare($sql);

                        if ($stmt->execute([
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
                        ])) {
                            $success_count++;
                            $dates_added[] = $date;
                        } else {
                            $error_count++;
                        }
                    } else {
                        // Record already exists
                        $error_count++;
                    }

                    $current_date->modify('+1 day');
                }

                $pdo->commit();

                if ($success_count > 0) {
                    $success_message = "Successfully added $success_count attendance records for $employee_name from $start_date to $end_date.";
                    if ($error_count > 0) {
                        $success_message .= " $error_count records were skipped (already exist).";
                    }
                } else {
                    $error_message = "No records were added. All dates already have attendance records.";
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Bulk add attendance error: " . $e->getMessage());
                $error_message = "Database error: Failed to add attendance records.";
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

    // Add UTF-8 BOM for Excel compatibility
    fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header row
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

    // Sample data rows
    $sampleData = [
        ['2024-01-15', 'EMP001', 'John Doe', 'Office of the Municipal Mayor', '08:00', '12:00', '13:00', '17:00'],
        ['2024-01-15', 'EMP002', 'Jane Smith', 'Human Resource Management Division', '08:15', '12:05', '13:10', '17:30'],
        ['2024-01-16', 'EMP001', 'John Doe', 'Office of the Municipal Mayor', '08:05', '12:00', '13:00', '17:00'],
        ['2024-01-16', 'EMP002', 'Jane Smith', 'Human Resource Management Division', '', '', '13:00', '17:00'],
    ];

    foreach ($sampleData as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit();
}

// =================================================================================
// --- Add Attendance (CREATE) ---
// =================================================================================

if (isset($_POST['add_attendance'])) {
    // Sanitize and retrieve input data
    $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_SANITIZE_STRING);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    $employee_name = filter_input(INPUT_POST, 'employee_name', FILTER_SANITIZE_STRING);
    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);

    // Time fields - accept empty values
    $am_time_in = !empty($_POST['am_time_in']) ? $_POST['am_time_in'] : null;
    $am_time_out = !empty($_POST['am_time_out']) ? $_POST['am_time_out'] : null;
    $pm_time_in = !empty($_POST['pm_time_in']) ? $_POST['pm_time_in'] : null;
    $pm_time_out = !empty($_POST['pm_time_out']) ? $_POST['pm_time_out'] : null;

    // Validate required fields
    if (empty($employee_id) || empty($date) || empty($employee_name)) {
        $error_message = "Error: Please fill all required fields (Employee ID, Date, and Employee Name).";
    } else {
        // Check if attendance already exists for this employee on this date
        try {
            $check_sql = "SELECT id FROM attendance WHERE employee_id = ? AND date = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$employee_id, $date]);

            if ($check_stmt->rowCount() > 0) {
                $error_message = "Error: Attendance record already exists for this employee on the selected date.";
            } else {
                // Calculate working hours
                $hours = calculateWorkingHours($am_time_in, $am_time_out, $pm_time_in, $pm_time_out);

                // Insert new attendance record
                $sql = "INSERT INTO attendance 
                        (date, employee_id, employee_name, department, am_time_in, am_time_out, pm_time_in, pm_time_out, ot_hours, under_time, total_hours) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $pdo->prepare($sql);

                if ($stmt->execute([
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
                ])) {
                    // Determine where to redirect based on current view
                    $redirect_url = $_SERVER['PHP_SELF'];
                    if (isset($_GET['view_attendance']) && $_GET['view_attendance'] == 'true' && isset($_GET['employee_id'])) {
                        $redirect_url .= '?view_attendance=true&employee_id=' . urlencode($_GET['employee_id']) . '&status=add_success';
                    } else {
                        $redirect_url .= '?status=add_success';
                    }
                    header("Location: " . $redirect_url);
                    exit();
                } else {
                    $error_message = "Error: Failed to add attendance record. Please try again.";
                }
            }
        } catch (PDOException $e) {
            error_log("Add attendance error: " . $e->getMessage());
            $error_message = "Database error: Failed to add attendance record.";
        }
    }
}

// =================================================================================
// --- Import Attendance Records ---
// =================================================================================

if (isset($_POST['import_attendance'])) {
    // Check if file was uploaded
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file'];
        $fileType = pathinfo($file['name'], PATHINFO_EXTENSION);

        // Check file extension
        $allowedExtensions = ['csv'];
        if (!in_array(strtolower($fileType), $allowedExtensions)) {
            $error_message = "Error: Only CSV files are allowed.";
        } else {
            $successCount = 0;
            $errorCount = 0;
            $duplicateCount = 0;
            $importErrors = [];

            try {
                // Open CSV file
                $handle = fopen($file['tmp_name'], 'r');

                if ($handle !== FALSE) {
                    // Skip header row
                    $header = fgetcsv($handle);

                    // Process each row
                    $rowNumber = 1;
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        $rowNumber++;

                        // Skip empty rows
                        if (empty(array_filter($data))) {
                            continue;
                        }

                        // Map CSV columns (adjust based on your template)
                        // Expected columns: date, employee_id, employee_name, department, am_time_in, am_time_out, pm_time_in, pm_time_out
                        $date = !empty($data[0]) ? trim($data[0]) : '';
                        $employee_id = !empty($data[1]) ? trim($data[1]) : '';
                        $employee_name = !empty($data[2]) ? trim($data[2]) : '';
                        $department = !empty($data[3]) ? trim($data[3]) : '';
                        $am_time_in = !empty($data[4]) ? trim($data[4]) : null;
                        $am_time_out = !empty($data[5]) ? trim($data[5]) : null;
                        $pm_time_in = !empty($data[6]) ? trim($data[6]) : null;
                        $pm_time_out = !empty($data[7]) ? trim($data[7]) : null;

                        // Validate required fields
                        if (empty($date) || empty($employee_id) || empty($employee_name)) {
                            $errorCount++;
                            $importErrors[] = "Row $rowNumber: Missing required fields (Date, Employee ID, or Name)";
                            continue;
                        }

                        // Validate date format
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                            $errorCount++;
                            $importErrors[] = "Row $rowNumber: Invalid date format (expected YYYY-MM-DD)";
                            continue;
                        }

                        // Check for duplicate record
                        $check_sql = "SELECT id FROM attendance WHERE employee_id = ? AND date = ?";
                        $check_stmt = $pdo->prepare($check_sql);
                        $check_stmt->execute([$employee_id, $date]);

                        if ($check_stmt->rowCount() > 0) {
                            $duplicateCount++;
                            continue;
                        }

                        // Calculate working hours
                        $hours = calculateWorkingHours($am_time_in, $am_time_out, $pm_time_in, $pm_time_out);

                        // Insert record
                        $sql = "INSERT INTO attendance 
                                (date, employee_id, employee_name, department, am_time_in, am_time_out, pm_time_in, pm_time_out, ot_hours, under_time, total_hours) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                        $stmt = $pdo->prepare($sql);

                        if ($stmt->execute([
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
                        ])) {
                            $successCount++;
                        } else {
                            $errorCount++;
                            $importErrors[] = "Row $rowNumber: Database error";
                        }
                    }
                    fclose($handle);
                }

                // Show import results
                if ($successCount > 0) {
                    $success_message = "Import completed successfully! $successCount records imported.";

                    if ($duplicateCount > 0) {
                        $success_message .= " $duplicateCount duplicate records were skipped.";
                    }

                    if ($errorCount > 0) {
                        $error_message = "$errorCount records failed to import.";
                        if (!empty($importErrors)) {
                            $error_message .= " Errors: " . implode(', ', array_slice($importErrors, 0, 3));
                            if (count($importErrors) > 3) {
                                $error_message .= " and " . (count($importErrors) - 3) . " more errors";
                            }
                        }
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
// --- Pagination Configuration ---
// =================================================================================

// Set number of records per page
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;

// Get current page from URL, default to 1
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// =================================================================================
// --- Fetch Total Number of Records ---
// =================================================================================

try {
    $count_sql = "SELECT COUNT(*) as total FROM attendance";
    $count_stmt = $pdo->query($count_sql);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Calculate total pages
    $total_pages = ceil($total_records / $records_per_page);

    // Ensure current page is within valid range
    if ($current_page < 1) {
        $current_page = 1;
    } elseif ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
    }
} catch (PDOException $e) {
    error_log("Count attendance error: " . $e->getMessage());
    $total_records = 0;
    $total_pages = 1;
}

// =================================================================================
// --- Fetch Attendance Data with Pagination ---
// =================================================================================

try {
    $sql = "SELECT * FROM attendance ORDER BY date DESC, employee_name ASC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch attendance error: " . $e->getMessage());
    $error_message = "Could not retrieve attendance records.";
    $attendance_records = [];
}

// =================================================================================
// --- NEW: Get All Employees for Auto-complete ---
// =================================================================================

$all_employees = getEmployeeTypes($pdo);

// Build current view parameters for redirects
$current_view_params = '';
if (isset($_GET['view_attendance']) && $_GET['view_attendance'] == 'true' && isset($_GET['employee_id'])) {
    $current_view_params = '?view_attendance=true&employee_id=' . urlencode($_GET['employee_id']);
} elseif (isset($_GET['view']) && $_GET['view'] == 'employees') {
    $current_view_params = '?view=employees';
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management - HRMS</title>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-blue': '#1048cb',
                        'secondary-gray': '#F5F7FA',
                        'accent-hover': '#0C379D',
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.1.1/flowbite.min.css" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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

        /* Custom scrollbar for table */
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

        /* Animation for modals */
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

        /* Improved button styles */
        .btn-primary {
            background: linear-gradient(135deg, #1048cb 0%, #0C379D 100%);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 72, 203, 0.3);
        }

        /* Card shadows */
        .card-shadow {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }

        /* Navigation Styles */
        :root {
            --primary: #1e40af;
            --secondary: #1e3a8a;
            --accent: #3b82f6;
            --gradient-nav: linear-gradient(90deg, #1e3a8a 0%, #1e40af 100%);
        }

        /* IMPROVED NAVBAR - Matches Image */
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

        /* Logo and Brand */
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

        /* Date & Time Display */
        .datetime-container {
            display: none;
            align-items: center;
            gap: 0.75rem;
        }

        /* Show datetime on medium screens and up */
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
            background: var(--gradient-nav);
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

        /* Mobile Menu Toggle */
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

        /* Sidebar Styles */
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
            background: var(--gradient-nav);
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

        /* Sidebar Overlay for Mobile */
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

        /* Main Content */
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

        /* Sidebar dropdown */
        .sidebar-item .chevron {
            transition: transform 0.3s ease;
        }

        .sidebar-item .chevron.rotated {
            transform: rotate(180deg);
        }

        /* Dropdown Menu in Sidebar */
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

        /* Mobile Brand Styling */
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

        /* Responsive table styles */
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

            /* Hide some columns on mobile */
            .mobile-hidden {
                display: none;
            }

            /* Adjust modal padding on mobile */
            .modal-mobile-padding {
                padding: 1rem;
            }

            .modal-mobile-full {
                width: 95vw;
                margin: 0 auto;
            }
        }

        /* Better responsive grid for filters */
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

        /* Import status styles */
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

        /* Pagination Styles */
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

        /* NEW: Bulk add table styles */
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

        /* Improved time inputs */
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

        /* Quick time buttons */
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

        /* Disabled future date styling */
        .future-date {
            opacity: 0.5;
            pointer-events: none;
        }

        .future-date input {
            background-color: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
        }

        /* NEW: Action button styles */
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

        /* View attendance modal styles */
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

        /* FIXED: Edit Modal Styles - Ensure it's visible and closable */
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
    </style>

</head>

<body class="bg-gray-50">
    <!-- Navigation Header -->
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
                    <img class="brand-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
                    <div class="brand-text">
                        <span class="brand-title">HR Management System</span>
                        <span class="brand-subtitle">Paluan Occidental Mindoro</span>
                    </div>
                </a>

                <!-- Logo and Brand (Mobile) -->
                <div class="mobile-brand">
                    <img class="brand-logo" src="https://cdn-ilebokm.nitrocdn.com/LDIERXKvnOnyQiQIfOmrlCQetXbgMMSd/assets/images/optimized/rev-c086d95/occidentalmindoro.gov.ph/wp-content/uploads/2022/07/Paluan-removebg-preview-1-1-1.png" alt="Logo" />
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

                <!-- Reports -->
                <a href="./paysliphistory.php" class="sidebar-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                </a>

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
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="text-white bg-blue-600 hover:bg-blue-700 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to Attendance
                        </a>
                        <a href="?view=employees" class="text-white bg-purple-600 hover:bg-purple-700 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out flex items-center">
                            <i class="fas fa-users mr-2"></i>
                            Employee Summary
                        </a>
                    </div>
                </div>

                <!-- Status Messages -->
                <?php if (isset($_GET['status'])): ?>
                    <?php
                    $status_param = $_GET['status'];
                    $message = '';
                    $color = '';
                    $icon = '';

                    switch ($status_param) {
                        case 'add_success':
                            $message = 'Attendance record added successfully!';
                            $color = 'green';
                            $icon = 'fa-check-circle';
                            break;
                        case 'edit_success':
                            $message = 'Attendance record updated successfully!';
                            $color = 'blue';
                            $icon = 'fa-check-circle';
                            break;
                        case 'delete_success':
                            $message = 'Attendance record deleted successfully!';
                            $color = 'red';
                            $icon = 'fa-check-circle';
                            break;
                        case 'edit_error':
                            $message = 'Error updating attendance record!';
                            $color = 'red';
                            $icon = 'fa-exclamation-circle';
                            break;
                    }

                    if ($message): ?>
                        <div class="p-3 md:p-4 mb-4 text-sm text-<?php echo $color; ?>-800 rounded-lg bg-<?php echo $color; ?>-50" role="alert">
                            <i class="fas <?php echo $icon; ?> mr-2"></i><?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Import Success Message -->
                <?php if (!empty($success_message)): ?>
                    <div class="p-3 md:p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 import-success" role="alert">
                        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Error Messages -->
                <?php if (!empty($error_message)): ?>
                    <div class="p-3 md:p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 import-error" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Employee Summary -->
                <div class="employee-summary">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                        <div>
                            <h2 class="text-xl font-bold mb-1"><?php echo htmlspecialchars($employee_details['full_name']); ?></h2>
                            <p class="text-white/80">ID: <?php echo htmlspecialchars($employee_details['employee_id']); ?> |
                                <?php echo htmlspecialchars($employee_details['type']); ?> Employee</p>
                        </div>
                        <div class="mt-4 md:mt-0 text-right">
                            <p class="text-white/80">Department: <?php echo htmlspecialchars($employee_details['department']); ?></p>
                            <p class="text-white/80">Total Records: <?php echo count($view_attendance_records); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Attendance Statistics -->
                <?php if (!empty($view_attendance_records)): ?>
                    <?php
                    $present_count = 0;
                    $absent_count = 0;
                    $late_count = 0;
                    $total_hours = 0;
                    $total_ot = 0;

                    foreach ($view_attendance_records as $record) {
                        if ($record['total_hours'] > 0) $present_count++;
                        if ($record['total_hours'] == 0) $absent_count++;
                        if ($record['under_time'] > 0) $late_count++;
                        $total_hours += $record['total_hours'];
                        $total_ot += $record['ot_hours'];
                    }

                    $present_rate = count($view_attendance_records) > 0 ? round(($present_count / count($view_attendance_records)) * 100, 1) : 0;
                    ?>

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
                                        <td class="px-4 py-3 text-gray-700 mobile-text-sm mobile-hidden"><?php echo $row['ot_hours']; ?>h</td>
                                        <td class="px-4 py-3 text-gray-700 mobile-text-sm mobile-hidden"><?php echo $row['under_time']; ?>h</td>
                                        <td class="px-4 py-3 font-semibold text-gray-900 mobile-text-sm mobile-hidden"><?php echo $row['total_hours']; ?>h</td>
                                        <td class="px-4 py-3 mobile-text-sm">
                                            <span class="attendance-status <?php echo $status_class; ?>">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 mobile-text-sm text-center">
                                            <div class="flex space-x-1 justify-center">
                                                <button type="button"
                                                    onclick="editAttendance(<?php echo $row['id']; ?>)"
                                                    class="action-btn edit-btn"
                                                    title="Edit Record">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button"
                                                    onclick="deleteAttendance(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['employee_name']); ?>', '<?php echo $row['date']; ?>')"
                                                    class="action-btn delete-btn"
                                                    title="Delete Record">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Export Button for this employee -->
                    <div class="flex justify-end mt-6">
                        <button type="button" onclick="exportEmployeeAttendance('<?php echo $employee_details['employee_id']; ?>', '<?php echo $employee_details['full_name']; ?>')"
                            class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out flex items-center">
                            <i class="fas fa-download mr-2"></i>
                            Export Attendance Data
                        </button>
                    </div>

                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-times text-5xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">No Attendance Records Found</h3>
                        <p class="text-gray-500">This employee doesn't have any attendance records yet.</p>
                        <div class="mt-4">
                            <button type="button" onclick="addAttendanceForEmployee('<?php echo $employee_details['employee_id']; ?>', '<?php echo htmlspecialchars($employee_details['full_name']); ?>', '<?php echo htmlspecialchars($employee_details['department']); ?>')"
                                class="text-white bg-green-600 hover:bg-green-700 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out flex items-center mx-auto">
                                <i class="fas fa-plus mr-2"></i>
                                Add First Attendance Record
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($show_employee_summary): ?>
            <!-- Employee Summary View -->
            <div class="p-4 md:p-6 bg-white rounded-lg shadow-md">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900">Employee Attendance Summary</h1>
                        <p class="text-gray-600">One row per employee with attendance statistics</p>
                    </div>
                    <div class="flex gap-2">
                        <a href="?view=attendance" class="text-white bg-blue-600 hover:bg-blue-700 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out flex items-center">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            Switch to Daily View
                        </a>
                    </div>
                </div>

                <!-- Status Messages -->
                <?php if (isset($_GET['status'])): ?>
                    <?php
                    $status_param = $_GET['status'];
                    $message = '';
                    $color = '';
                    $icon = '';

                    switch ($status_param) {
                        case 'add_success':
                            $message = 'Attendance record added successfully!';
                            $color = 'green';
                            $icon = 'fa-check-circle';
                            break;
                        case 'edit_success':
                            $message = 'Attendance record updated successfully!';
                            $color = 'blue';
                            $icon = 'fa-check-circle';
                            break;
                        case 'delete_success':
                            $message = 'Attendance record deleted successfully!';
                            $color = 'red';
                            $icon = 'fa-check-circle';
                            break;
                    }

                    if ($message): ?>
                        <div class="p-3 md:p-4 mb-4 text-sm text-<?php echo $color; ?>-800 rounded-lg bg-<?php echo $color; ?>-50" role="alert">
                            <i class="fas <?php echo $icon; ?> mr-2"></i><?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Import Success Message -->
                <?php if (!empty($success_message)): ?>
                    <div class="p-3 md:p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 import-success" role="alert">
                        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Error Messages -->
                <?php if (!empty($error_message)): ?>
                    <div class="p-3 md:p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 import-error" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="bg-gray-50 p-4 rounded-lg mb-6 card-shadow">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Employees</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="emp_search" class="block text-sm font-medium text-gray-700 mb-1">Search Employee:</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-search text-gray-400 text-sm"></i>
                                </div>
                                <input type="search" id="emp_search"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5"
                                    placeholder="Search by name or ID...">
                            </div>
                        </div>

                        <div>
                            <label for="emp_department" class="block text-sm font-medium text-gray-700 mb-1">Department:</label>
                            <select id="emp_department"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="">All Departments</option>
                                <option value="Office of the Municipal Mayor">Office of the Municipal Mayor</option>
                                <option value="Human Resource Management Division">Human Resource Management Division</option>
                                <option value="Business Permit and Licensing Division">Business Permit and Licensing Division</option>
                                <option value="Sangguniang Bayan Office">Sangguniang Bayan Office</option>
                                <option value="Office of the Municipal Accountant">Office of the Municipal Accountant</option>
                                <option value="Office of the Assessor">Office of the Assessor</option>
                                <option value="Municipal Budget Office">Municipal Budget Office</option>
                                <option value="Municipal Planning and Development Office">Municipal Planning and Development Office</option>
                                <option value="Municipal Engineering Office">Municipal Engineering Office</option>
                                <option value="Municipal Disaster Risk Reduction and Management Office">Municipal Disaster Risk Reduction and Management Office</option>
                                <option value="Municipal Social Welfare and Development Office">Municipal Social Welfare and Development Office</option>
                                <option value="Municipal Environment and Natural Resources Office">Municipal Environment and Natural Resources Office</option>
                                <option value="Office of the Municipal Agriculturist">Office of the Municipal Agriculturist</option>
                                <option value="Municipal General Services Office">Municipal General Services Office</option>
                                <option value="Municipal Public Employment Service Office">Municipal Public Employment Service Office</option>
                                <option value="Municipal Health Office">Municipal Health Office</option>
                                <option value="Municipal Treasurer's Office">Municipal Treasurer's Office</option>
                            </select>
                        </div>

                        <div>
                            <label for="emp_status" class="block text-sm font-medium text-gray-700 mb-1">Attendance Status:</label>
                            <select id="emp_status"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="">All Employees</option>
                                <option value="has_attendance">Has Attendance Records</option>
                                <option value="no_attendance">No Attendance Records</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Employee Summary Table -->
                <div class="table-container overflow-x-auto rounded-lg border border-gray-200">
                    <table class="w-full text-sm text-left text-gray-900" id="employeeSummaryTable">
                        <thead class="text-xs text-white uppercase bg-blue-600">
                            <tr>
                                <th scope="col" class="px-4 py-3">Employee ID</th>
                                <th scope="col" class="px-4 py-3">Name</th>
                                <th scope="col" class="px-4 py-3">Department</th>
                                <th scope="col" class="px-4 py-3">Employee Type</th>
                                <th scope="col" class="px-4 py-3">Total Records</th>
                                <th scope"col" class="px-4 py-3">Last Attendance</th>
                                <th scope="col" class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (!empty($employee_summary)): ?>
                                <?php
                                foreach ($employee_summary as $employee):
                                    // Get attendance statistics for this employee
                                    $stats_sql = "SELECT 
                                        COUNT(*) as total_records,
                                        MAX(date) as last_date,
                                        SUM(total_hours) as total_hours,
                                        SUM(ot_hours) as total_ot,
                                        SUM(under_time) as total_undertime
                                        FROM attendance 
                                        WHERE employee_id = ?";
                                    $stats_stmt = $pdo->prepare($stats_sql);
                                    $stats_stmt->execute([$employee['employee_id']]);
                                    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

                                    $total_records = $stats['total_records'] ?? 0;
                                    $last_date = $stats['last_date'] ? date('M d, Y', strtotime($stats['last_date'])) : 'No records';
                                    $total_hours = $stats['total_hours'] ?? 0;
                                ?>
                                    <tr class="bg-white hover:bg-gray-50 transition-colors duration-150">
                                        <td class="px-4 py-3 font-medium text-gray-900">
                                            <?php echo htmlspecialchars($employee['employee_id']); ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($employee['full_name']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            <?php echo htmlspecialchars($employee['department']); ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                                <?php echo isset($employee['type']) && $employee['type'] == 'Permanent' ? 'bg-green-100 text-green-800' : (isset($employee['type']) && $employee['type'] == 'Job Order' ? 'bg-blue-100 text-blue-800' : (isset($employee['type']) && $employee['type'] == 'Contractual' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800')); ?>">
                                                <?php echo isset($employee['type']) ? $employee['type'] : 'Unknown'; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if ($total_records > 0): ?>
                                                <div class="flex items-center">
                                                    <span class="font-semibold text-gray-900"><?php echo $total_records; ?></span>
                                                    <?php if ($total_hours > 0): ?>
                                                        <span class="ml-2 text-xs text-gray-500">
                                                            (<?php echo round($total_hours, 1); ?> hours)
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400">No records</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            <?php echo $last_date; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex space-x-2 justify-center">
                                                <?php if ($total_records > 0): ?>
                                                    <a href="?view_attendance=true&employee_id=<?php echo urlencode($employee['employee_id']); ?>"
                                                        class="action-btn view-btn"
                                                        title="View Attendance Records">
                                                        <i class="fas fa-eye mr-1"></i> View
                                                    </a>
                                                <?php else: ?>
                                                    <button type="button"
                                                        onclick="addAttendanceForEmployee('<?php echo htmlspecialchars($employee['employee_id']); ?>', '<?php echo htmlspecialchars($employee['full_name']); ?>', '<?php echo htmlspecialchars($employee['department']); ?>')"
                                                        class="action-btn edit-btn"
                                                        title="Add Attendance">
                                                        <i class="fas fa-plus mr-1"></i> Add
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan='7' class='text-center py-8 text-gray-500'>
                                        <i class='fas fa-users text-4xl mb-2 text-gray-300'></i>
                                        <p>No employees found.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <!-- Main Attendance Management Page -->
            <div class="p-4 md:p-6 bg-white rounded-lg shadow-md">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <h1 class="text-xl md:text-2xl font-bold text-gray-900">Attendance Management</h1>
                    <a href="?view=employees" class="text-white bg-purple-600 hover:bg-purple-700 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out flex items-center">
                        <i class="fas fa-users mr-2"></i>
                        Employee Summary View
                    </a>
                </div>

                <!-- Status Messages -->
                <?php if (isset($_GET['status'])): ?>
                    <?php
                    $status_param = $_GET['status'];
                    $message = '';
                    $color = '';
                    $icon = '';

                    switch ($status_param) {
                        case 'add_success':
                            $message = 'Attendance record added successfully!';
                            $color = 'green';
                            $icon = 'fa-check-circle';
                            break;
                        case 'edit_success':
                            $message = 'Attendance record updated successfully!';
                            $color = 'blue';
                            $icon = 'fa-check-circle';
                            break;
                        case 'delete_success':
                            $message = 'Attendance record deleted successfully!';
                            $color = 'red';
                            $icon = 'fa-check-circle';
                            break;
                    }

                    if ($message): ?>
                        <div class="p-3 md:p-4 mb-4 text-sm text-<?php echo $color; ?>-800 rounded-lg bg-<?php echo $color; ?>-50" role="alert">
                            <i class="fas <?php echo $icon; ?> mr-2"></i><?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Import Success Message -->
                <?php if (!empty($success_message)): ?>
                    <div class="p-3 md:p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 import-success" role="alert">
                        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Error Messages -->
                <?php if (!empty($error_message)): ?>
                    <div class="p-3 md:p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 import-error" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
                    <!-- Filter Section -->
                    <div class="bg-gray-50 p-4 rounded-lg mb-6 card-shadow">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Records</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 responsive-grid">
                            <div>
                                <label for="from_date" class="block text-sm font-medium text-gray-700 mb-1">From Date:</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <i class="fas fa-calendar text-blue-500 text-sm"></i>
                                    </div>
                                    <input type="date" id="from_date"
                                        class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5"
                                        value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>">
                                </div>
                            </div>

                            <div>
                                <label for="to_date" class="block text-sm font-medium text-gray-700 mb-1">To Date:</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <i class="fas fa-calendar text-blue-500 text-sm"></i>
                                    </div>
                                    <input type="date" id="to_date"
                                        class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5"
                                        value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>

                            <div>
                                <label for="employee_name" class="block text-sm font-medium text-gray-700 mb-1">Employee Name:</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <i class="fas fa-search text-gray-400 text-sm"></i>
                                    </div>
                                    <input type="search" id="employee_name"
                                        class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5"
                                        placeholder="Search by name...">
                                </div>
                            </div>

                            <div>
                                <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department:</label>
                                <select id="department"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                    <option selected value="">All Departments</option>
                                    <option value="Office of the Municipal Mayor">Office of the Municipal Mayor</option>
                                    <option value="Human Resource Management Division">Human Resource Management Division</option>
                                    <option value="Business Permit and Licensing Division">Business Permit and Licensing Division</option>
                                    <option value="Sangguniang Bayan Office">Sangguniang Bayan Office</option>
                                    <option value="Office of the Municipal Accountant">Office of the Municipal Accountant</option>
                                    <option value="Office of the Assessor">Office of the Assessor</option>
                                    <option value="Municipal Budget Office">Municipal Budget Office</option>
                                    <option value="Municipal Planning and Development Office">Municipal Planning and Development Office</option>
                                    <option value="Municipal Engineering Office">Municipal Engineering Office</option>
                                    <option value="Municipal Disaster Risk Reduction and Management Office">Municipal Disaster Risk Reduction and Management Office</option>
                                    <option value="Municipal Social Welfare and Development Office">Municipal Social Welfare and Development Office</option>
                                    <option value="Municipal Environment and Natural Resources Office">Municipal Environment and Natural Resources Office</option>
                                    <option value="Office of the Municipal Agriculturist">Office of the Municipal Agriculturist</option>
                                    <option value="Municipal General Services Office">Municipal General Services Office</option>
                                    <option value="Municipal Public Employment Service Office">Municipal Public Employment Service Office</option>
                                    <option value="Municipal Health Office">Municipal Health Office</option>
                                    <option value="Municipal Treasurer's Office">Municipal Treasurer's Office</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <div class="flex items-center">
                            <span class="text-sm text-gray-600">
                                Showing <span class="font-semibold"><?php echo min(count($attendance_records), $records_per_page); ?></span> of <span class="font-semibold"><?php echo $total_records; ?></span> total records
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-2 mobile-stack">
                            <button type="button" data-modal-target="addAttendanceModal" data-modal-toggle="addAttendanceModal"
                                class="btn-primary text-white focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out mobile-full flex items-center justify-center">
                                <i class="fas fa-plus mr-2"></i>
                                <span>Add Attendance</span>
                            </button>
                            <button type="button" data-modal-target="bulkAddAttendanceModal" data-modal-toggle="bulkAddAttendanceModal"
                                class="text-white bg-yellow-600 hover:bg-yellow-700 focus:ring-4 focus:ring-yellow-300 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out mobile-full flex items-center justify-center">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Monthly DTR Entry</span>
                            </button>
                            <button type="button" data-modal-target="importAttendanceModal" data-modal-toggle="importAttendanceModal"
                                class="text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:ring-purple-300 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out mobile-full flex items-center justify-center">
                                <i class="fas fa-file-import mr-2"></i>
                                <span>Import</span>
                            </button>
                            <button type="button" id="exportBtn"
                                class="text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out mobile-full flex items-center justify-center">
                                <i class="fas fa-download mr-2"></i>
                                <span>Export</span>
                            </button>
                        </div>
                    </div>

                    <!-- Attendance Table -->
                    <div class="table-container overflow-x-auto rounded-lg border border-gray-200 mobile-table-container">
                        <table class="w-full text-sm text-left text-gray-900 mobile-table">
                            <thead class="text-xs text-white uppercase bg-blue-600">
                                <tr>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm">Date</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm">ID</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm">Name</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm mobile-hidden">Department</th>
                                    <th scope="col" class="px-4 py-3 text-center mobile-text-sm" colspan="2">AM</th>
                                    <th scope="col" class="px-4 py-3 text-center mobile-text-sm" colspan="2">PM</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm mobile-hidden">OT</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm mobile-hidden">UnderTime</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm mobile-hidden">Total</th>
                                    <th scope="col" class="px-4 py-3 mobile-text-sm text-center">Actions</th>
                                </tr>
                                <tr>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th class="mobile-hidden"></th>
                                    <th scope="col" class="px-3 py-2 text-center bg-blue-700 border-t border-blue-500 mobile-text-sm">In</th>
                                    <th scope="col" class="px-3 py-2 text-center bg-blue-700 border-t border-blue-500 mobile-text-sm">Out</th>
                                    <th scope="col" class="px-3 py-2 text-center bg-blue-700 border-t border-blue-500 mobile-text-sm">In</th>
                                    <th scope="col" class="px-3 py-2 text-center bg-blue-700 border-t border-blue-500 mobile-text-sm">Out</th>
                                    <th class="mobile-hidden"></th>
                                    <th class="mobile-hidden"></th>
                                    <th class="mobile-hidden"></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($attendance_records)): ?>
                                    <?php foreach ($attendance_records as $row): ?>
                                        <tr class="bg-white hover:bg-gray-50 transition-colors duration-150">
                                            <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap mobile-text-sm">
                                                <?php echo date('M d, Y', strtotime($row['date'])); ?>
                                            </td>
                                            <td class="px-4 py-3 text-gray-700 mobile-text-sm"><?php echo htmlspecialchars($row['employee_id']); ?></td>
                                            <td class="px-4 py-3 text-gray-700 mobile-text-sm"><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                            <td class="px-4 py-3 text-gray-700 mobile-text-sm mobile-hidden"><?php echo htmlspecialchars($row['department']); ?></td>
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
                                            <td class="px-4 py-3 text-gray-700 mobile-text-sm mobile-hidden"><?php echo $row['ot_hours']; ?>h</td>
                                            <td class="px-4 py-3 text-gray-700 mobile-text-sm mobile-hidden"><?php echo $row['under_time']; ?>h</td>
                                            <td class="px-4 py-3 font-semibold text-gray-900 mobile-text-sm mobile-hidden"><?php echo $row['total_hours']; ?>h</td>
                                            <td class="px-4 py-3 mobile-text-sm text-center">
                                                <div class="flex space-x-1 justify-center">
                                                    <a href="?view_attendance=true&employee_id=<?php echo urlencode($row['employee_id']); ?>"
                                                        class="action-btn view-btn"
                                                        title="View All Records">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button type="button"
                                                        onclick="editAttendance(<?php echo $row['id']; ?>)"
                                                        class="action-btn edit-btn"
                                                        title="Edit Record">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button"
                                                        onclick="deleteAttendance(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['employee_name']); ?>', '<?php echo $row['date']; ?>')"
                                                        class="action-btn delete-btn"
                                                        title="Delete Record">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan='12' class='text-center py-8 text-gray-500'>
                                            <i class='fas fa-inbox text-4xl mb-2 text-gray-300'></i>
                                            <p>No attendance records found.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex flex-col sm:flex-row justify-between items-center mt-6 gap-4">
                        <div class="text-sm text-gray-700">
                            Page <span class="font-semibold"><?php echo $current_page; ?></span> of <span class="font-semibold"><?php echo $total_pages; ?></span>
                        </div>
                        <div class="flex space-x-1">
                            <!-- Previous Button -->
                            <?php if ($current_page > 1): ?>
                                <a href="?page=<?php echo $current_page - 1; ?>" class="pagination-link">
                                    <i class="fas fa-chevron-left mr-1"></i> Previous
                                </a>
                            <?php else: ?>
                                <span class="pagination-link disabled">
                                    <i class="fas fa-chevron-left mr-1"></i> Previous
                                </span>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php
                            // Show page numbers
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);

                            for ($page = $start_page; $page <= $end_page; $page++):
                            ?>
                                <a href="?page=<?php echo $page; ?>"
                                    class="pagination-link <?php echo ($page == $current_page) ? 'active' : ''; ?>">
                                    <?php echo $page; ?>
                                </a>
                            <?php endfor; ?>

                            <!-- Next Button -->
                            <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?php echo $current_page + 1; ?>" class="pagination-link">
                                    Next <i class="fas fa-chevron-right ml-1"></i>
                                </a>
                            <?php else: ?>
                                <span class="pagination-link disabled">
                                    Next <i class="fas fa-chevron-right ml-1"></i>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Records per page selector -->
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-700">Show:</span>
                            <select id="records_per_page" class="text-sm border border-gray-300 rounded p-1">
                                <option value="10" <?php echo $records_per_page == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $records_per_page == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $records_per_page == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $records_per_page == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                            <span class="text-sm text-gray-700">records per page</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Add Attendance Modal -->
    <div id="addAttendanceModal" tabindex="-1" aria-hidden="true" class="fixed top-0 left-0 right-0 z-50 hidden w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative w-full max-w-2xl max-h-full modal-animation modal-mobile-full">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between p-5 border-b rounded-t bg-blue-600 text-white">
                    <h3 class="text-lg md:text-xl font-semibold">
                        <i class="fas fa-plus-circle mr-2"></i>Add New Attendance Record
                    </h3>
                    <button type="button" class="text-white bg-transparent hover:bg-blue-700 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center transition duration-150 ease-in-out" data-modal-hide="addAttendanceModal">
                        <i class="fas fa-times w-5 h-5"></i>
                    </button>
                </div>
                <form action="" method="POST" class="p-4 md:p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="employee_id" class="block mb-2 text-sm font-medium text-gray-900">Employee ID *</label>
                            <input type="text" name="employee_id" id="employee_id" placeholder="Enter employee ID"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                required
                                list="employeeList">
                            <datalist id="employeeList">
                                <?php foreach ($all_employees as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>">
                                        <?php echo htmlspecialchars($emp['full_name'] . ' - ' . $emp['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div>
                            <label for="date" class="block mb-2 text-sm font-medium text-gray-900">Date *</label>
                            <input type="date" name="date" id="date"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                required
                                value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="employee_name" class="block mb-2 text-sm font-medium text-gray-900">Employee Name *</label>
                            <input type="text" name="employee_name" id="employee_name" placeholder="Enter employee name"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"
                                required
                                list="employeeNameList">
                            <datalist id="employeeNameList">
                                <?php foreach ($all_employees as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['full_name']); ?>">
                                        <?php echo htmlspecialchars($emp['employee_id'] . ' - ' . $emp['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div>
                            <label for="department" class="block mb-2 text-sm font-medium text-gray-900">Department</label>
                            <select name="department" id="department"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                <option value="">Select Department</option>
                                <option value="Office of the Municipal Mayor">Office of the Municipal Mayor</option>
                                <option value="Human Resource Management Division">Human Resource Management Division</option>
                                <option value="Business Permit and Licensing Division">Business Permit and Licensing Division</option>
                                <option value="Sangguniang Bayan Office">Sangguniang Bayan Office</option>
                                <option value="Office of the Municipal Accountant">Office of the Municipal Accountant</option>
                                <option value="Office of the Assessor">Office of the Assessor</option>
                                <option value="Municipal Budget Office">Municipal Budget Office</option>
                                <option value="Municipal Planning and Development Office">Municipal Planning and Development Office</option>
                                <option value="Municipal Engineering Office">Municipal Engineering Office</option>
                                <option value="Municipal Disaster Risk Reduction and Management Office">Municipal Disaster Risk Reduction and Management Office</option>
                                <option value="Municipal Social Welfare and Development Office">Municipal Social Welfare and Development Office</option>
                                <option value="Municipal Environment and Natural Resources Office">Municipal Environment and Natural Resources Office</option>
                                <option value="Office of the Municipal Agriculturist">Office of the Municipal Agriculturist</option>
                                <option value="Municipal General Services Office">Municipal General Services Office</option>
                                <option value="Municipal Public Employment Service Office">Municipal Public Employment Service Office</option>
                                <option value="Municipal Health Office">Municipal Health Office</option>
                                <option value="Municipal Treasurer's Office">Municipal Treasurer's Office</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-t pt-4">
                        <h6 class="text-base md:text-lg font-semibold text-blue-600 mb-3 flex items-center">
                            <i class="fas fa-sun mr-2"></i>Morning Shift (AM)
                        </h6>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="am_time_in" class="block mb-2 text-sm font-medium text-gray-900">Time-in</label>
                                <input type="time" name="am_time_in" id="am_time_in"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                            </div>
                            <div>
                                <label for="am_time_out" class="block mb-2 text-sm font-medium text-gray-900">Time-out</label>
                                <input type="time" name="am_time_out" id="am_time_out"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                            </div>
                        </div>
                    </div>

                    <div class="border-t pt-4">
                        <h6 class="text-base md:text-lg font-semibold text-blue-600 mb-3 flex items-center">
                            <i class="fas fa-moon mr-2"></i>Afternoon Shift (PM)
                        </h6>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="pm_time_in" class="block mb-2 text-sm font-medium text-gray-900">Time-in</label>
                                <input type="time" name="pm_time_in" id="pm_time_in"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                            </div>
                            <div>
                                <label for="pm_time_out" class="block mb-2 text-sm font-medium text-gray-900">Time-out</label>
                                <input type="time" name="pm_time_out" id="pm_time_out"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end p-4 md:p-6 space-x-3 border-t border-gray-200 rounded-b">
                        <button type="submit" name="add_attendance" class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:outline-none focus:ring-green-300 font-medium rounded-lg text-sm px-4 py-2.5 text-center flex items-center">
                            <i class="fas fa-save mr-2"></i>Save Attendance
                        </button>
                        <button type="button" data-modal-hide="addAttendanceModal" class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-4 py-2.5 hover:text-gray-900 focus:z-10">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Monthly DTR Entry Modal -->
    <div id="bulkAddAttendanceModal" tabindex="-1" aria-hidden="true" class="fixed top-0 left-0 right-0 z-50 hidden w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative w-full max-w-6xl max-h-full modal-animation modal-mobile-full">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between p-5 border-b rounded-t bg-yellow-600 text-white">
                    <h3 class="text-lg md:text-xl font-semibold">
                        <i class="fas fa-calendar-alt mr-2"></i>Monthly DTR Entry (Daily Time Records)
                    </h3>
                    <button type="button" class="text-white bg-transparent hover:bg-yellow-700 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center transition duration-150 ease-in-out" data-modal-hide="bulkAddAttendanceModal">
                        <i class="fas fa-times w-5 h-5"></i>
                    </button>
                </div>
                <form action="" method="POST" class="p-4 md:p-6 space-y-4" id="bulkAttendanceForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div>
                            <label for="bulk_employee_id" class="block mb-2 text-sm font-medium text-gray-900">Employee ID *</label>
                            <input type="text" name="employee_id" id="bulk_employee_id" placeholder="Enter employee ID"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-yellow-500 focus:border-yellow-500 block w-full p-2.5"
                                required
                                list="employeeListBulk">
                            <datalist id="employeeListBulk">
                                <?php foreach ($all_employees as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['employee_id']); ?>">
                                        <?php echo htmlspecialchars($emp['full_name'] . ' - ' . $emp['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div>
                            <label for="bulk_employee_name" class="block mb-2 text-sm font-medium text-gray-900">Employee Name *</label>
                            <input type="text" name="employee_name" id="bulk_employee_name" placeholder="Enter employee name"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-yellow-500 focus:border-yellow-500 block w-full p-2.5"
                                required
                                list="employeeNameListBulk">
                            <datalist id="employeeNameListBulk">
                                <?php foreach ($all_employees as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['full_name']); ?>">
                                        <?php echo htmlspecialchars($emp['employee_id'] . ' - ' . $emp['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div>
                            <label for="bulk_department" class="block mb-2 text-sm font-medium text-gray-900">Department *</label>
                            <select name="department" id="bulk_department"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-yellow-500 focus:border-yellow-500 block w-full p-2.5" required>
                                <option value="">Select Department</option>
                                <option value="Office of the Municipal Mayor">Office of the Municipal Mayor</option>
                                <option value="Human Resource Management Division">Human Resource Management Division</option>
                                <option value="Business Permit and Licensing Division">Business Permit and Licensing Division</option>
                                <option value="Sangguniang Bayan Office">Sangguniang Bayan Office</option>
                                <option value="Office of the Municipal Accountant">Office of the Municipal Accountant</option>
                                <option value="Office of the Assessor">Office of the Assessor</option>
                                <option value="Municipal Budget Office">Municipal Budget Office</option>
                                <option value="Municipal Planning and Development Office">Municipal Planning and Development Office</option>
                                <option value="Municipal Engineering Office">Municipal Engineering Office</option>
                                <option value="Municipal Disaster Risk Reduction and Management Office">Municipal Disaster Risk Reduction and Management Office</option>
                                <option value="Municipal Social Welfare and Development Office">Municipal Social Welfare and Development Office</option>
                                <option value="Municipal Environment and Natural Resources Office">Municipal Environment and Natural Resources Office</option>
                                <option value="Office of the Municipal Agriculturist">Office of the Municipal Agriculturist</option>
                                <option value="Municipal General Services Office">Municipal General Services Office</option>
                                <option value="Municipal Public Employment Service Office">Municipal Public Employment Service Office</option>
                                <option value="Municipal Health Office">Municipal Health Office</option>
                                <option value="Municipal Treasurer's Office">Municipal Treasurer's Office</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="month_select" class="block mb-2 text-sm font-medium text-gray-900">Month</label>
                                <select id="month_select" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-yellow-500 focus:border-yellow-500 block w-full p-2.5">
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
                                <label for="year_select" class="block mb-2 text-sm font-medium text-gray-900">Year</label>
                                <select id="year_select" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-yellow-500 focus:border-yellow-500 block w-full p-2.5">
                                    <?php
                                    $currentYear = date('Y');
                                    for ($year = $currentYear - 1; $year <= $currentYear; $year++): ?>
                                        <option value="<?php echo $year; ?>" <?php echo $year == $currentYear ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Fill Options -->
                    <div class="bg-yellow-50 p-3 rounded-lg mb-4">
                        <h6 class="text-sm font-semibold text-yellow-800 mb-2">Quick Fill Options:</h6>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="quick-time-btn bg-blue-100 hover:bg-blue-200 border border-blue-300" onclick="fillStandardTimes()">
                                Standard (8-12, 1-5)
                            </button>
                            <button type="button" class="quick-time-btn bg-green-100 hover:bg-green-200 border border-green-300" onclick="fillEarlyTimes()">
                                Early (7:30-12, 1-5:30)
                            </button>
                            <button type="button" class="quick-time-btn bg-purple-100 hover:bg-purple-200 border border-purple-300" onclick="fillLateTimes()">
                                Late (8:30-12, 1-5:30)
                            </button>
                            <button type="button" class="quick-time-btn bg-red-100 hover:bg-red-200 border border-red-300" onclick="clearAllTimes()">
                                Clear All
                            </button>
                            <button type="button" class="quick-time-btn bg-yellow-100 hover:bg-yellow-200 border border-yellow-300" onclick="markWeekendsAsLeave()">
                                Mark Weekends as Leave
                            </button>
                            <button type="button" class="quick-time-btn bg-indigo-100 hover:bg-indigo-200 border border-indigo-300" onclick="applyTemplateFromImage()">
                                Apply Sample Template
                            </button>
                        </div>
                    </div>

                    <!-- Daily Time Entry Table -->
                    <div class="border-t pt-4">
                        <h6 class="text-base md:text-lg font-semibold text-yellow-600 mb-3 flex items-center">
                            <i class="fas fa-clock mr-2"></i>Daily Time Entry (Enter times for each day)
                        </h6>

                        <div class="bulk-table-container">
                            <table class="w-full text-sm text-left text-gray-900">
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
                                System will calculate OT and Undertime automatically.
                            </p>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="border-t pt-4">
                        <h6 class="text-base md:text-lg font-semibold text-yellow-600 mb-3 flex items-center">
                            <i class="fas fa-chart-bar mr-2"></i>Summary
                        </h6>
                        <div id="bulkSummary" class="p-3 bg-gray-50 rounded-lg text-sm text-gray-600">
                            <p>Select a month and year to see summary.</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end p-4 md:p-6 space-x-3 border-t border-gray-200 rounded-b">
                        <input type="hidden" name="start_date" id="hidden_start_date" value="">
                        <input type="hidden" name="end_date" id="hidden_end_date" value="">
                        <button type="submit" name="bulk_add_attendance" class="text-white bg-yellow-600 hover:bg-yellow-700 focus:ring-4 focus:outline-none focus:ring-yellow-300 font-medium rounded-lg text-sm px-4 py-2.5 text-center flex items-center">
                            <i class="fas fa-calendar-plus mr-2"></i>Save Monthly DTR
                        </button>
                        <button type="button" data-modal-hide="bulkAddAttendanceModal" class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-4 py-2.5 hover:text-gray-900 focus:z-10">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Attendance Modal -->
    <div id="importAttendanceModal" tabindex="-1" aria-hidden="true" class="fixed top-0 left-0 right-0 z-50 hidden w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative w-full max-w-md max-h-full modal-animation modal-mobile-full">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between p-5 border-b rounded-t bg-purple-600 text-white">
                    <h3 class="text-lg md:text-xl font-semibold">
                        <i class="fas fa-file-import mr-2"></i>Import Attendance Records
                    </h3>
                    <button type="button" class="text-white bg-transparent hover:bg-purple-700 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center transition duration-150 ease-in-out" data-modal-hide="importAttendanceModal">
                        <i class="fas fa-times w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-4 md:p-6 space-y-4">
                    <!-- Tab Navigation -->
                    <div class="border-b border-gray-200">
                        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="importTab" role="tablist">
                            <li class="mr-2" role="presentation">
                                <button class="inline-block p-4 border-b-2 rounded-t-lg active" id="xlsx-tab" type="button" role="tab" aria-controls="xlsx" aria-selected="true">
                                    <i class="fas fa-file-excel mr-2 text-green-600"></i>XLSX/DTR Format
                                </button>
                            </li>
                            <li class="mr-2" role="presentation">
                                <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300" id="csv-tab" type="button" role="tab" aria-controls="csv" aria-selected="false">
                                    <i class="fas fa-file-csv mr-2 text-blue-600"></i>CSV Format
                                </button>
                            </li>
                        </ul>
                    </div>

                    <!-- XLSX Import Tab -->
                    <div class="p-4" id="xlsx" role="tabpanel" aria-labelledby="xlsx-tab">
                        <form id="xlsxImportForm" method="POST" enctype="multipart/form-data">
                            <div class="space-y-4">
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-gray-900">Download Sample Template</label>
                                    <a href="download_sample_xlsx.php" class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-4 py-2.5 inline-flex items-center">
                                        <i class="fas fa-download mr-2"></i>Download XLSX Template
                                    </a>
                                    <p class="mt-1 text-xs text-gray-500">Format matches the DTR export from your system</p>
                                </div>

                                <div>
                                    <label for="xlsx_file" class="block mb-2 text-sm font-medium text-gray-900">Upload XLSX/DTR File *</label>
                                    <div class="flex items-center justify-center w-full">
                                        <label for="xlsx_file" class="flex flex-col items-center justify-center w-full h-32 border-2 border-purple-300 border-dashed rounded-lg cursor-pointer bg-purple-50 hover:bg-purple-100 file-upload-area">
                                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                <i class="fas fa-file-excel text-3xl text-purple-600 mb-2"></i>
                                                <p class="mb-2 text-sm text-gray-700"><span class="font-semibold text-purple-600">Click to upload</span> or drag and drop</p>
                                                <p class="text-xs text-gray-500">XLSX files from attendance system (max 10MB)</p>
                                            </div>
                                            <input id="xlsx_file" name="xlsx_file" type="file" class="hidden" accept=".xlsx,.xls" />
                                        </label>
                                    </div>
                                    <p class="mt-2 text-xs text-gray-600">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Supports DTR exports with multiple employees and daily time records
                                    </p>
                                </div>

                                <div id="xlsxFileInfo" class="hidden p-3 bg-purple-50 rounded-lg">
                                    <div class="flex items-center">
                                        <i class="fas fa-file-excel text-purple-600 mr-2"></i>
                                        <span id="xlsxFileName" class="text-sm font-medium text-gray-700"></span>
                                        <span id="xlsxFileSize" class="text-xs text-gray-500 ml-2"></span>
                                    </div>
                                </div>

                                <!-- Progress Bar -->
                                <div id="xlsxProgressContainer" class="hidden">
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm font-medium text-purple-700">Importing...</span>
                                        <span id="xlsxProgressPercent" class="text-sm font-medium text-purple-700">0%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div id="xlsxProgressBar" class="bg-purple-600 h-2.5 rounded-full" style="width: 0%"></div>
                                    </div>
                                    <p id="xlsxStatusMessage" class="mt-2 text-xs text-gray-600"></p>
                                </div>

                                <div class="flex items-center p-3 text-sm text-purple-700 bg-purple-50 rounded-lg" role="alert">
                                    <i class="fas fa-info-circle mr-2 flex-shrink-0"></i>
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
                            </div>

                            <div class="flex items-center justify-end space-x-3 mt-6 pt-4 border-t">
                                <button type="submit" id="importXlsxBtn" class="text-white bg-purple-600 hover:bg-purple-700 focus:ring-4 focus:outline-none focus:ring-purple-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fas fa-upload mr-2"></i>
                                    <span>Import XLSX</span>
                                </button>
                                <button type="button" data-modal-hide="importAttendanceModal" class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-purple-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- CSV Import Tab (Existing) -->
                    <div class="hidden p-4" id="csv" role="tabpanel" aria-labelledby="csv-tab">
                        <form action="" method="POST" enctype="multipart/form-data">
                            <div class="space-y-4">
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-gray-900">Download Template</label>
                                    <a href="?download_template=true" class="text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2.5 inline-flex items-center">
                                        <i class="fas fa-download mr-2"></i>Download CSV Template
                                    </a>
                                </div>

                                <div>
                                    <label for="csv_file" class="block mb-2 text-sm font-medium text-gray-900">Upload CSV File *</label>
                                    <div class="flex items-center justify-center w-full">
                                        <label for="csv_file" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 file-upload-area">
                                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                <i class="fas fa-cloud-upload-alt text-2xl text-gray-400 mb-2"></i>
                                                <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                                <p class="text-xs text-gray-500">CSV file (max 5MB)</p>
                                            </div>
                                            <input id="csv_file" name="csv_file" type="file" class="hidden" accept=".csv" />
                                        </label>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Required columns: date, employee_id, employee_name, department, am_time_in, am_time_out, pm_time_in, pm_time_out
                                    </p>
                                </div>

                                <div id="fileInfo" class="hidden p-3 bg-blue-50 rounded-lg">
                                    <div class="flex items-center">
                                        <i class="fas fa-file-csv text-blue-500 mr-2"></i>
                                        <span id="fileName" class="text-sm font-medium text-gray-700"></span>
                                        <span id="fileSize" class="text-xs text-gray-500 ml-2"></span>
                                    </div>
                                </div>

                                <div class="flex items-center p-3 text-sm text-blue-700 bg-blue-50 rounded-lg" role="alert">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <div>
                                        <span class="font-medium">Note:</span>
                                        <ul class="mt-1.5 ml-4 list-disc text-xs">
                                            <li>Time format: HH:MM (24-hour)</li>
                                            <li>Date format: YYYY-MM-DD</li>
                                            <li>Duplicate records will be skipped</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-3 mt-6 pt-4 border-t">
                                <button type="submit" name="import_attendance" class="text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center flex items-center">
                                    <i class="fas fa-upload mr-2"></i>Import CSV
                                </button>
                                <button type="button" data-modal-hide="importAttendanceModal" class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Results Modal -->
    <div id="importResultsModal" tabindex="-1" aria-hidden="true" class="fixed top-0 left-0 right-0 z-50 hidden w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative w-full max-w-2xl max-h-full modal-animation">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between p-5 border-b rounded-t" id="importResultsHeader">
                    <h3 class="text-lg md:text-xl font-semibold text-white">
                        <i class="fas fa-check-circle mr-2"></i>Import Results
                    </h3>
                    <button type="button" class="text-white bg-transparent hover:bg-opacity-80 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center" data-modal-hide="importResultsModal">
                        <i class="fas fa-times w-5 h-5"></i>
                    </button>
                </div>
                <div class="p-6" id="importResultsContent">
                    <!-- Results will be populated by JavaScript -->
                </div>
                <div class="flex items-center justify-end p-6 pt-0 border-t">
                    <button type="button" data-modal-hide="importResultsModal" class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-purple-300 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- FIXED: Edit Attendance Modal - Pre-loaded for immediate use -->
    <div id="editAttendanceModal" tabindex="-1" aria-hidden="true" class="fixed top-0 left-0 right-0 z-50 hidden w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-[calc(100%-1rem)] max-h-full" style="background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
        <div class="relative w-full max-w-2xl max-h-full modal-animation modal-mobile-full modal-content mx-auto my-8">
            <div class="relative bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between p-5 border-b rounded-t bg-blue-600 text-white">
                    <h3 class="text-lg md:text-xl font-semibold">
                        <i class="fas fa-edit mr-2"></i>Edit Attendance Record
                    </h3>
                    <button type="button" class="text-white bg-transparent hover:bg-blue-700 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center transition duration-150 ease-in-out close-edit-modal" onclick="closeEditModal()">
                        <i class="fas fa-times w-5 h-5"></i>
                    </button>
                </div>
                <div id="editFormContent" class="p-4 md:p-6">
                    <div class="text-center py-8">
                        <div class="spinner-border inline-block w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-2 text-gray-600">Loading record data...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.1.1/flowbite.min.js"></script>
    <script>
        // ============================================
        // XLSX IMPORT FUNCTIONALITY
        // ============================================

        // Tab switching
        document.addEventListener('DOMContentLoaded', function() {
            const xlsxTab = document.getElementById('xlsx-tab');
            const csvTab = document.getElementById('csv-tab');
            const xlsxPanel = document.getElementById('xlsx');
            const csvPanel = document.getElementById('csv');

            if (xlsxTab && csvTab) {
                xlsxTab.addEventListener('click', function() {
                    xlsxTab.classList.add('active', 'border-purple-600', 'text-purple-600');
                    xlsxTab.classList.remove('border-transparent');
                    csvTab.classList.remove('active', 'border-blue-600', 'text-blue-600');
                    csvTab.classList.add('border-transparent');
                    xlsxPanel.classList.remove('hidden');
                    csvPanel.classList.add('hidden');
                });

                csvTab.addEventListener('click', function() {
                    csvTab.classList.add('active', 'border-blue-600', 'text-blue-600');
                    csvTab.classList.remove('border-transparent');
                    xlsxTab.classList.remove('active', 'border-purple-600', 'text-purple-600');
                    xlsxTab.classList.add('border-transparent');
                    csvPanel.classList.remove('hidden');
                    xlsxPanel.classList.add('hidden');
                });
            }

            // XLSX File upload preview
            const xlsxFileInput = document.getElementById('xlsx_file');
            const xlsxFileInfo = document.getElementById('xlsxFileInfo');
            const xlsxFileName = document.getElementById('xlsxFileName');
            const xlsxFileSize = document.getElementById('xlsxFileSize');

            if (xlsxFileInput) {
                xlsxFileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const maxSize = 10 * 1024 * 1024; // 10MB
                        if (file.size > maxSize) {
                            showNotification('File size exceeds 10MB limit. Please choose a smaller file.', 'error');
                            this.value = '';
                            xlsxFileInfo.classList.add('hidden');
                            return;
                        }

                        xlsxFileInfo.classList.remove('hidden');
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
                    } else {
                        xlsxFileInfo.classList.add('hidden');
                    }
                });
            }

            // XLSX Import Form Submission
            const xlsxImportForm = document.getElementById('xlsxImportForm');
            if (xlsxImportForm) {
                xlsxImportForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const fileInput = document.getElementById('xlsx_file');
                    if (!fileInput.files[0]) {
                        showNotification('Please select an XLSX file to import', 'error');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('xlsx_file', fileInput.files[0]);

                    const importBtn = document.getElementById('importXlsxBtn');
                    const progressContainer = document.getElementById('xlsxProgressContainer');
                    const progressBar = document.getElementById('xlsxProgressBar');
                    const progressPercent = document.getElementById('xlsxProgressPercent');
                    const statusMessage = document.getElementById('xlsxStatusMessage');

                    importBtn.disabled = true;
                    progressContainer.classList.remove('hidden');
                    progressBar.style.width = '0%';
                    progressPercent.textContent = '0%';
                    statusMessage.textContent = 'Uploading file...';

                    fetch('import_attendance_xlsx.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            progressBar.style.width = '100%';
                            progressPercent.textContent = '100%';

                            setTimeout(() => {
                                progressContainer.classList.add('hidden');
                                importBtn.disabled = false;

                                if (data.success) {
                                    showImportResults(data);
                                    // Reset form
                                    xlsxImportForm.reset();
                                    xlsxFileInfo.classList.add('hidden');

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
                            progressContainer.classList.add('hidden');
                            importBtn.disabled = false;
                            showNotification('Error importing file: ' + error.message, 'error');
                        });
                });
            }
        });

        // Show import results in modal
        function showImportResults(data) {
            const modal = document.getElementById('importResultsModal');
            const header = document.getElementById('importResultsHeader');
            const content = document.getElementById('importResultsContent');

            // Set header color based on success
            if (data.success) {
                header.className = 'flex items-center justify-between p-5 border-b rounded-t bg-green-600 text-white';
            } else {
                header.className = 'flex items-center justify-between p-5 border-b rounded-t bg-red-600 text-white';
            }

            // Build results HTML
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
                <h5 class="text-sm font-semibold text-gray-700 mb-2">Error Details:</h5>
                <div class="max-h-40 overflow-y-auto bg-red-50 p-3 rounded-lg">
                    <ul class="text-xs text-red-600 list-disc list-inside">
                        ${data.error_messages.map(msg => `<li>${msg}</li>`).join('')}
                    </ul>
                </div>
            </div>
        `;
            }

            // Show sample of imported records
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
                                <th class="text-left pb-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.records.slice(0, 5).map(rec => `
                                <tr class="border-t border-gray-200">
                                    <td class="py-1">${rec.employee}</td>
                                    <td class="py-1">${rec.date}</td>
                                    <td class="py-1 text-green-600">${rec.status}</td>
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

            // Show modal
            const modalInstance = new Modal(modal);
            modalInstance.show();
        }
        document.addEventListener('DOMContentLoaded', function() {
            // Update date and time in navbar
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

            // Sidebar toggle for mobile
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebarContainer = document.getElementById('sidebar-container');
            const sidebarOverlay = document.getElementById('sidebar-overlay');

            if (sidebarToggle && sidebarContainer) {
                sidebarToggle.addEventListener('click', function() {
                    sidebarContainer.classList.toggle('active');
                    sidebarOverlay.classList.toggle('active');
                });

                sidebarOverlay.addEventListener('click', function() {
                    sidebarContainer.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                });
            }

            // User menu toggle
            const userMenuButton = document.getElementById('user-menu-button');
            const userDropdown = document.getElementById('user-dropdown');

            if (userMenuButton && userDropdown) {
                userMenuButton.addEventListener('click', function() {
                    userDropdown.classList.toggle('active');
                    this.classList.toggle('active');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userMenuButton.contains(event.target) && !userDropdown.contains(event.target)) {
                        userDropdown.classList.remove('active');
                        userMenuButton.classList.remove('active');
                    }
                });
            }

            // Payroll dropdown toggle in sidebar
            const payrollToggle = document.getElementById('payroll-toggle');
            const payrollDropdown = document.getElementById('payroll-dropdown');

            if (payrollToggle && payrollDropdown) {
                payrollToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Toggle the 'open' class
                    payrollDropdown.classList.toggle('open');

                    // Toggle chevron rotation
                    const chevron = this.querySelector('.chevron');
                    if (chevron) {
                        chevron.classList.toggle('rotated');
                    }
                });
            }

            // File upload preview
            const csvFileInput = document.getElementById('csv_file');
            const fileInfoDiv = document.getElementById('fileInfo');
            const fileNameSpan = document.getElementById('fileName');
            const fileSizeSpan = document.getElementById('fileSize');

            if (csvFileInput && fileInfoDiv) {
                csvFileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        // Check file size (5MB limit)
                        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                        if (file.size > maxSize) {
                            alert('File size exceeds 5MB limit. Please choose a smaller file.');
                            this.value = ''; // Clear the file input
                            fileInfoDiv.classList.add('hidden');
                            return;
                        }

                        // Check file extension
                        const fileName = file.name.toLowerCase();
                        if (!fileName.endsWith('.csv')) {
                            alert('Only CSV files are allowed. Please select a CSV file.');
                            this.value = ''; // Clear the file input
                            fileInfoDiv.classList.add('hidden');
                            return;
                        }

                        fileInfoDiv.classList.remove('hidden');
                        fileNameSpan.textContent = file.name;

                        // Format file size
                        const fileSize = file.size;
                        let sizeText = '';
                        if (fileSize < 1024) {
                            sizeText = fileSize + ' bytes';
                        } else if (fileSize < 1048576) {
                            sizeText = (fileSize / 1024).toFixed(2) + ' KB';
                        } else {
                            sizeText = (fileSize / 1048576).toFixed(2) + ' MB';
                        }
                        fileSizeSpan.textContent = sizeText;
                    } else {
                        fileInfoDiv.classList.add('hidden');
                    }
                });
            }

            // Search functionality
            const searchInput = document.getElementById('employee_name');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = document.querySelectorAll('tbody tr');

                    rows.forEach(row => {
                        const nameCell = row.querySelector('td:nth-child(3)'); // Name column
                        if (nameCell && nameCell.textContent.toLowerCase().includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }

            // Department filter
            const departmentFilter = document.getElementById('department');
            if (departmentFilter) {
                departmentFilter.addEventListener('change', function() {
                    const selectedDept = this.value;
                    const rows = document.querySelectorAll('tbody tr');

                    rows.forEach(row => {
                        const deptCell = row.querySelector('td:nth-child(4)'); // Department column
                        if (selectedDept === '' || (deptCell && deptCell.textContent === selectedDept)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }

            // Date filter
            const fromDateFilter = document.getElementById('from_date');
            const toDateFilter = document.getElementById('to_date');

            function filterByDate() {
                const fromDate = fromDateFilter ? new Date(fromDateFilter.value) : null;
                const toDate = toDateFilter ? new Date(toDateFilter.value) : null;
                const rows = document.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    const dateCell = row.querySelector('td:nth-child(1)'); // Date column
                    if (dateCell) {
                        const rowDateText = dateCell.textContent.trim();
                        const rowDate = new Date(rowDateText);

                        let shouldShow = true;

                        if (fromDate && rowDate < fromDate) {
                            shouldShow = false;
                        }

                        if (toDate && rowDate > toDate) {
                            shouldShow = false;
                        }

                        row.style.display = shouldShow ? '' : 'none';
                    }
                });
            }

            if (fromDateFilter) {
                fromDateFilter.addEventListener('change', filterByDate);
            }

            if (toDateFilter) {
                toDateFilter.addEventListener('change', filterByDate);
            }

            // ============================================
            // AUTO-FILL EMPLOYEE INFO FUNCTIONS
            // ============================================

            // Function to auto-fill employee name and department when ID is entered
            function autoFillEmployeeInfo() {
                const bulkEmployeeIdInput = document.getElementById('bulk_employee_id');
                const bulkEmployeeNameInput = document.getElementById('bulk_employee_name');
                const bulkDepartmentSelect = document.getElementById('bulk_department');

                if (bulkEmployeeIdInput && bulkEmployeeNameInput && bulkDepartmentSelect) {
                    bulkEmployeeIdInput.addEventListener('blur', async function() {
                        const employeeId = this.value.trim();

                        if (employeeId.length === 0) {
                            return;
                        }

                        try {
                            // Show loading state
                            this.classList.add('opacity-75');

                            // Fetch employee data via AJAX
                            const response = await fetch('get_employee_info.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'employee_id=' + encodeURIComponent(employeeId)
                            });

                            const data = await response.json();

                            if (data.success) {
                                // Auto-fill the fields
                                bulkEmployeeNameInput.value = data.employee.full_name;
                                bulkDepartmentSelect.value = data.employee.department;

                                // Show success notification
                                showNotification('Employee information loaded successfully!', 'success');
                            } else {
                                // Clear fields if employee not found
                                bulkEmployeeNameInput.value = '';
                                bulkDepartmentSelect.value = '';

                                // Show error notification
                                showNotification('Employee ID not found. Please enter a valid ID.', 'error');
                            }
                        } catch (error) {
                            console.error('Error fetching employee info:', error);
                            showNotification('Error loading employee information. Please try again.', 'error');
                        } finally {
                            // Remove loading state
                            this.classList.remove('opacity-75');
                        }
                    });

                    // Also add event listener for Enter key
                    bulkEmployeeIdInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            this.blur(); // Trigger blur event
                        }
                    });
                }
            }

            // Function to auto-fill for regular attendance modal
            function autoFillEmployeeInfoRegular() {
                const employeeIdInput = document.getElementById('employee_id');
                const employeeNameInput = document.getElementById('employee_name');
                const departmentSelect = document.getElementById('department');

                if (employeeIdInput && employeeNameInput && departmentSelect) {
                    employeeIdInput.addEventListener('blur', async function() {
                        const employeeId = this.value.trim();

                        if (employeeId.length === 0) {
                            return;
                        }

                        try {
                            // Show loading state
                            this.classList.add('opacity-75');

                            // Fetch employee data via AJAX
                            const response = await fetch('get_employee_info.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'employee_id=' + encodeURIComponent(employeeId)
                            });

                            const data = await response.json();

                            if (data.success) {
                                // Auto-fill the fields
                                employeeNameInput.value = data.employee.full_name;
                                departmentSelect.value = data.employee.department;

                                // Show success notification
                                showNotification('Employee information loaded successfully!', 'success');
                            } else {
                                // Clear fields if employee not found
                                employeeNameInput.value = '';
                                departmentSelect.value = '';

                                // Show error notification
                                showNotification('Employee ID not found. Please enter a valid ID.', 'error');
                            }
                        } catch (error) {
                            console.error('Error fetching employee info:', error);
                            showNotification('Error loading employee information. Please try again.', 'error');
                        } finally {
                            // Remove loading state
                            this.classList.remove('opacity-75');
                        }
                    });

                    // Also add event listener for Enter key
                    employeeIdInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            this.blur(); // Trigger blur event
                        }
                    });
                }
            }

            // Initialize auto-fill functions
            autoFillEmployeeInfo();
            autoFillEmployeeInfoRegular();

            // ============================================
            // MONTHLY DTR ENTRY FUNCTIONS
            // ============================================

            // Month/Year selection
            const monthSelect = document.getElementById('month_select');
            const yearSelect = document.getElementById('year_select');
            const dailyTimeTable = document.getElementById('dailyTimeTable');
            const bulkSummary = document.getElementById('bulkSummary');
            const hiddenStartDate = document.getElementById('hidden_start_date');
            const hiddenEndDate = document.getElementById('hidden_end_date');

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

                // Set hidden dates
                const startDate = new Date(year, month - 1, 1);
                const endDate = new Date(year, month, 0); // Last day of month

                hiddenStartDate.value = formatDate(startDate);
                hiddenEndDate.value = formatDate(endDate);

                // Generate table rows
                let tableHTML = '';
                let totalDays = endDate.getDate();
                let workDays = 0;
                let weekendDays = 0;
                let holidayDays = 0;
                let futureDays = 0;

                for (let day = 1; day <= totalDays; day++) {
                    const currentDate = new Date(year, month - 1, day);
                    const dayOfWeek = currentDate.getDay(); // 0 = Sunday, 1 = Monday, etc.
                    const dayName = getDayName(dayOfWeek);
                    const dateString = formatDate(currentDate);
                    const formattedDate = formatDisplayDate(currentDate);

                    // Check if it's a future date
                    const isFuture = isFutureDate(currentDate);

                    // Determine row class
                    let rowClass = '';
                    let status = 'Work Day';

                    if (isFuture) {
                        rowClass = 'future-date';
                        status = 'Future Date';
                        futureDays++;
                    } else if (dayOfWeek === 0 || dayOfWeek === 6) {
                        rowClass = 'weekend-row';
                        status = 'Weekend';
                        weekendDays++;
                    } else {
                        workDays++;
                    }

                    // Check for holidays (you can expand this list)
                    const holidays = [
                        '01-01', // New Year
                        '04-09', // Araw ng Kagitingan
                        '05-01', // Labor Day
                        '06-12', // Independence Day
                        '08-21', // Ninoy Aquino Day
                        '08-26', // National Heroes Day
                        '11-30', // Bonifacio Day
                        '12-25', // Christmas
                        '12-30' // Rizal Day
                    ];

                    const monthDay = String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                    if (holidays.includes(monthDay)) {
                        rowClass = 'holiday-row';
                        status = 'Holiday';
                        holidayDays++;
                        if (!isFuture && !(dayOfWeek === 0 || dayOfWeek === 6)) {
                            workDays--; // Adjust work days count
                        }
                    }

                    tableHTML += `
                    <tr class="${rowClass}">
                        <td class="px-3 py-2 font-medium text-gray-900 text-center">${formattedDate}</td>
                        <td class="px-3 py-2 text-gray-700 text-center">${dayName}</td>
                        <td class="px-2 py-1 text-center">
                            <input type="time" name="am_time_in[${dateString}]" 
                                   class="time-input border border-gray-300 rounded p-1" 
                                   placeholder="08:00"
                                   data-day="${day}"
                                   ${isFuture ? 'disabled' : ''}>
                        </td>
                        <td class="px-2 py-1 text-center">
                            <input type="time" name="am_time_out[${dateString}]" 
                                   class="time-input border border-gray-300 rounded p-1" 
                                   placeholder="12:00"
                                   data-day="${day}"
                                   ${isFuture ? 'disabled' : ''}>
                        </td>
                        <td class="px-2 py-1 text-center">
                            <input type="time" name="pm_time_in[${dateString}]" 
                                   class="time-input border border-gray-300 rounded p-1" 
                                   placeholder="13:00"
                                   data-day="${day}"
                                   ${isFuture ? 'disabled' : ''}>
                        </td>
                        <td class="px-2 py-1 text-center">
                            <input type="time" name="pm_time_out[${dateString}]" 
                                   class="time-input border border-gray-300 rounded p-1" 
                                   placeholder="17:00"
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

                // Update summary
                updateSummary(workDays, weekendDays, holidayDays, totalDays, futureDays);
            }

            function updateSummary(workDays, weekendDays, holidayDays, totalDays, futureDays) {
                bulkSummary.innerHTML = `
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <div class="bg-blue-50 p-2 rounded text-center">
                        <div class="text-2xl font-bold text-blue-600">${totalDays}</div>
                        <div class="text-xs text-blue-800">Total Days</div>
                    </div>
                    <div class="bg-green-50 p-2 rounded text-center">
                        <div class="text-2xl font-bold text-green-600">${workDays}</div>
                        <div class="text-xs text-green-800">Work Days</div>
                    </div>
                    <div class="bg-yellow-50 p-2 rounded text-center">
                        <div class="text-2xl font-bold text-yellow-600">${weekendDays}</div>
                        <div class="text-xs text-yellow-800">Weekends</div>
                    </div>
                    <div class="bg-red-50 p-2 rounded text-center">
                        <div class="text-2xl font-bold text-red-600">${holidayDays}</div>
                        <div class="text-xs text-red-800">Holidays</div>
                    </div>
                    <div class="bg-gray-100 p-2 rounded text-center">
                        <div class="text-2xl font-bold text-gray-600">${futureDays}</div>
                        <div class="text-xs text-gray-800">Future Days</div>
                    </div>
                </div>
                <p class="mt-3 text-sm text-gray-600">
                    <i class="fas fa-info-circle text-yellow-500 mr-1"></i>
                    <strong>Note:</strong> Future dates are disabled. Enter times for work days. Leave blank for weekends/holidays.
                    System will calculate OT and Undertime automatically.
                </p>
            `;
            }

            // ============================================
            // QUICK FILL FUNCTIONS - FIXED
            // ============================================

            // Standard (8-12, 1-5)
            function fillStandardTimes() {
                fillAllTimes('08:00', '12:00', '13:00', '17:00');
            }

            // Early (7:30-12, 1-5:30)
            function fillEarlyTimes() {
                fillAllTimes('07:30', '12:00', '13:00', '17:30');
            }

            // Late (8:30-12, 1-5:30)
            function fillLateTimes() {
                fillAllTimes('08:30', '12:00', '13:00', '17:30');
            }

            // Main fill function
            function fillAllTimes(amIn, amOut, pmIn, pmOut) {
                const rows = document.querySelectorAll('#dailyTimeTable tr');

                rows.forEach(row => {
                    const isWeekend = row.classList.contains('weekend-row');
                    const isHoliday = row.classList.contains('holiday-row');
                    const isFuture = row.classList.contains('future-date');

                    // Don't fill weekends, holidays or future dates
                    if (!isWeekend && !isHoliday && !isFuture) {
                        const inputs = row.querySelectorAll('input[type="time"]:not(:disabled)');
                        if (inputs.length >= 4) {
                            inputs[0].value = amIn;
                            inputs[1].value = amOut;
                            inputs[2].value = pmIn;
                            inputs[3].value = pmOut;
                        }
                    }
                });

                // Show notification
                showNotification(`Filled all work days with: AM ${amIn}-${amOut}, PM ${pmIn}-${pmOut}`, 'success');
            }

            // Clear All
            function clearAllTimes() {
                const timeInputs = document.querySelectorAll('#dailyTimeTable input[type="time"]:not(:disabled)');
                timeInputs.forEach(input => input.value = '');
                showNotification('Cleared all enabled time fields', 'success');
            }

            // Mark Weekends as Leave
            function markWeekendsAsLeave() {
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
            }

            // Apply Sample Template from your image
            function applyTemplateFromImage() {
                // Sample times from your image (12th-16th, 19th-23rd, 26th-30th)
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

                // Clear all enabled fields first
                clearAllTimes();

                // Apply sample data
                Object.keys(sampleData).forEach(day => {
                    const data = sampleData[day];
                    const inputs = document.querySelectorAll(`#dailyTimeTable input[data-day="${day}"]:not(:disabled)`);

                    if (inputs.length >= 4) {
                        inputs[0].value = data.am_in;
                        inputs[1].value = data.am_out;
                        inputs[2].value = data.pm_in;
                        inputs[3].value = data.pm_out;
                    }
                });

                showNotification('Sample template applied! Days 1-11, 17-18, 24-25, and 31 are left blank as holidays/leaves.', 'success');
            }

            // Notification helper
            function showNotification(message, type = 'success') {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = `fixed top-4 right-4 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white px-4 py-2 rounded-lg shadow-lg z-50 transform transition-transform duration-300 translate-y-0`;
                notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;

                // Add to body
                document.body.appendChild(notification);

                // Remove after 3 seconds
                setTimeout(() => {
                    notification.classList.add('translate-y-[-100px]');
                    setTimeout(() => {
                        if (notification.parentNode) {
                            document.body.removeChild(notification);
                        }
                    }, 300);
                }, 3000);
            }

            // ============================================
            // ATTACH EVENT LISTENERS
            // ============================================

            // Event listeners for month/year changes
            if (monthSelect) monthSelect.addEventListener('change', generateMonthTable);
            if (yearSelect) yearSelect.addEventListener('change', generateMonthTable);

            // Make sure future dates are disabled in dropdowns
            function updateMonthYearOptions() {
                const now = new Date();
                const currentYear = now.getFullYear();
                const currentMonth = now.getMonth() + 1; // January is 0

                if (yearSelect) {
                    Array.from(yearSelect.options).forEach(option => {
                        const yearValue = parseInt(option.value);
                        if (yearValue > currentYear) {
                            option.disabled = true;
                        }
                    });
                }

                if (monthSelect && parseInt(yearSelect.value) === currentYear) {
                    Array.from(monthSelect.options).forEach(option => {
                        const monthValue = parseInt(option.value);
                        if (monthValue > currentMonth) {
                            option.disabled = true;
                        }
                    });
                }
            }

            // Initial generation
            if (monthSelect && yearSelect) {
                updateMonthYearOptions();
                generateMonthTable();
            }

            // Export functionality
            const exportBtn = document.getElementById('exportBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    // Create CSV content
                    let csv = 'Date,Employee ID,Employee Name,Department,AM In,AM Out,PM In,PM Out,OT Hours,UnderTime Hours,Total Hours\n';

                    document.querySelectorAll('tbody tr').forEach(row => {
                        if (row.style.display !== 'none') {
                            const cells = row.querySelectorAll('td');
                            let rowData = [];

                            cells.forEach((cell, index) => {
                                let cellText = cell.textContent.trim();

                                // Remove time format indicators
                                cellText = cellText.replace('--', '');
                                cellText = cellText.replace('h', '');
                                cellText = cellText.trim();

                                // Wrap in quotes if contains comma
                                if (cellText.includes(',')) {
                                    cellText = '"' + cellText + '"';
                                }

                                rowData.push(cellText);
                            });

                            csv += rowData.join(',') + '\n';
                        }
                    });

                    // Create download link
                    const blob = new Blob([csv], {
                        type: 'text/csv'
                    });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'attendance_export_' + new Date().toISOString().split('T')[0] + '.csv';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                });
            }

            // Records per page selector
            const recordsPerPageSelect = document.getElementById('records_per_page');
            if (recordsPerPageSelect) {
                recordsPerPageSelect.addEventListener('change', function() {
                    const selectedValue = this.value;
                    // Store in localStorage for user preference
                    localStorage.setItem('attendance_records_per_page', selectedValue);
                    // Redirect with new page size (reset to page 1)
                    window.location.href = '?page=1&per_page=' + selectedValue;
                });
            }

            // Apply saved records per page preference
            const savedPerPage = localStorage.getItem('attendance_records_per_page');
            if (savedPerPage && recordsPerPageSelect) {
                recordsPerPageSelect.value = savedPerPage;
            }

            // ============================================
            // EMPLOYEE SUMMARY VIEW FILTERS
            // ============================================

            // Employee search filter
            const empSearch = document.getElementById('emp_search');
            if (empSearch) {
                empSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = document.querySelectorAll('#employeeSummaryTable tbody tr');

                    rows.forEach(row => {
                        const idCell = row.querySelector('td:nth-child(1)');
                        const nameCell = row.querySelector('td:nth-child(2)');

                        const idText = idCell ? idCell.textContent.toLowerCase() : '';
                        const nameText = nameCell ? nameCell.textContent.toLowerCase() : '';

                        if (idText.includes(searchTerm) || nameText.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }

            // Department filter for employee summary
            const empDeptFilter = document.getElementById('emp_department');
            if (empDeptFilter) {
                empDeptFilter.addEventListener('change', function() {
                    const selectedDept = this.value;
                    const rows = document.querySelectorAll('#employeeSummaryTable tbody tr');

                    rows.forEach(row => {
                        const deptCell = row.querySelector('td:nth-child(3)');
                        if (selectedDept === '' || (deptCell && deptCell.textContent === selectedDept)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }

            // Attendance status filter
            const empStatusFilter = document.getElementById('emp_status');
            if (empStatusFilter) {
                empStatusFilter.addEventListener('change', function() {
                    const selectedStatus = this.value;
                    const rows = document.querySelectorAll('#employeeSummaryTable tbody tr');

                    rows.forEach(row => {
                        const recordsCell = row.querySelector('td:nth-child(5)');
                        let shouldShow = true;

                        if (selectedStatus === 'has_attendance') {
                            const recordsText = recordsCell ? recordsCell.textContent : '';
                            shouldShow = !recordsText.includes('No records');
                        } else if (selectedStatus === 'no_attendance') {
                            const recordsText = recordsCell ? recordsCell.textContent : '';
                            shouldShow = recordsText.includes('No records');
                        }

                        row.style.display = shouldShow ? '' : 'none';
                    });
                });
            }

            // ============================================
            // ADD ATTENDANCE FOR SPECIFIC EMPLOYEE
            // ============================================

            window.addAttendanceForEmployee = function(employeeId, employeeName, department) {
                // Fill the add attendance form
                const employeeIdInput = document.getElementById('employee_id');
                const employeeNameInput = document.getElementById('employee_name');
                const departmentSelect = document.getElementById('department');

                if (employeeIdInput) employeeIdInput.value = employeeId;
                if (employeeNameInput) employeeNameInput.value = employeeName;
                if (departmentSelect) departmentSelect.value = department;

                // Open the modal
                const modal = document.getElementById('addAttendanceModal');
                if (modal) {
                    const modalInstance = new Modal(modal);
                    modalInstance.show();
                }

                // Focus on the date field
                setTimeout(() => {
                    const dateField = document.getElementById('date');
                    if (dateField) dateField.focus();
                }, 100);
            };

            // ============================================
            // FIXED: EDIT ATTENDANCE FUNCTION - Now properly closable
            // ============================================

            window.editAttendance = function(attendanceId) {
                const modal = document.getElementById('editAttendanceModal');

                if (!modal) {
                    console.error('Edit modal not found');
                    return;
                }

                // Show the modal using Flowbite's Modal class
                const modalInstance = new Modal(modal);
                modalInstance.show();

                // Reset the content to loading state
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

                // Fetch record data via AJAX
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

                            // Get current view parameters for redirect
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
            // FIXED: CLOSE EDIT MODAL FUNCTION
            // ============================================

            window.closeEditModal = function() {
                const modal = document.getElementById('editAttendanceModal');
                if (modal) {
                    // Use Flowbite's Modal class to hide
                    try {
                        const modalInstance = new Modal(modal);
                        modalInstance.hide();
                    } catch (e) {
                        // Fallback: manually hide
                        modal.classList.add('hidden');
                        modal.setAttribute('aria-hidden', 'true');
                    }
                }
            };

            // Add event listeners to all close buttons for the edit modal
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
                    // Submit delete request
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
            // EXPORT EMPLOYEE ATTENDANCE
            // ============================================

            window.exportEmployeeAttendance = function(employeeId, employeeName) {
                // Create a form to submit the export request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'export_employee_attendance.php';

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'employee_id';
                idInput.value = employeeId;

                const nameInput = document.createElement('input');
                nameInput.type = 'hidden';
                nameInput.name = 'employee_name';
                nameInput.value = employeeName;

                form.appendChild(idInput);
                form.appendChild(nameInput);
                document.body.appendChild(form);
                form.submit();
            };

            // Make quick fill functions available globally
            window.fillStandardTimes = fillStandardTimes;
            window.fillEarlyTimes = fillEarlyTimes;
            window.fillLateTimes = fillLateTimes;
            window.clearAllTimes = clearAllTimes;
            window.markWeekendsAsLeave = markWeekendsAsLeave;
            window.applyTemplateFromImage = applyTemplateFromImage;
            window.showNotification = showNotification;
        });
    </script>
    <script>
        // XLSX Import Form Submission - DEBUG VERSION
        document.addEventListener('DOMContentLoaded', function() {
            const xlsxImportForm = document.getElementById('xlsxImportForm');
            if (xlsxImportForm) {
                xlsxImportForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const fileInput = document.getElementById('xlsx_file');
                    if (!fileInput.files[0]) {
                        alert('Please select an XLSX file to import');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('xlsx_file', fileInput.files[0]);

                    const importBtn = document.getElementById('importXlsxBtn');
                    const progressContainer = document.getElementById('xlsxProgressContainer');
                    const progressBar = document.getElementById('xlsxProgressBar');
                    const progressPercent = document.getElementById('xlsxProgressPercent');
                    const statusMessage = document.getElementById('xlsxStatusMessage');

                    importBtn.disabled = true;
                    progressContainer.classList.remove('hidden');
                    progressBar.style.width = '0%';
                    progressPercent.textContent = '0%';
                    statusMessage.textContent = 'Uploading file...';

                    fetch('import_attendance_xlsx.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            console.log('Import response:', data); // DEBUG: See what's coming back

                            progressBar.style.width = '100%';
                            progressPercent.textContent = '100%';

                            setTimeout(() => {
                                progressContainer.classList.add('hidden');
                                importBtn.disabled = false;

                                if (data.success) {
                                    alert('SUCCESS: ' + data.message + '\nImported: ' + data.imported + ' records');
                                    // Reload page after successful import
                                    setTimeout(() => {
                                        location.reload();
                                    }, 2000);
                                } else {
                                    // Show detailed error
                                    let errorMsg = 'ERROR: ' + data.message;
                                    if (data.debug) {
                                        errorMsg += '\n\nDEBUG INFO:\n' + JSON.stringify(data.debug, null, 2);
                                    }
                                    if (data.error_messages && data.error_messages.length > 0) {
                                        errorMsg += '\n\nErrors:\n' + data.error_messages.join('\n');
                                    }
                                    alert(errorMsg);
                                }
                            }, 500);
                        })
                        .catch(error => {
                            progressContainer.classList.add('hidden');
                            importBtn.disabled = false;
                            alert('Network error: ' + error.message);
                        });
                });
            }
        });
    </script>
</body>

</html>
<?php
// Close PDO connection
$pdo = null;
?>