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
// --- NEW: Bulk Add Attendance for 2 Weeks ---
// =================================================================================

if (isset($_POST['bulk_add_attendance'])) {
    $employee_id = filter_input(INPUT_POST, 'employee_id', FILTER_SANITIZE_STRING);
    $employee_name = filter_input(INPUT_POST, 'employee_name', FILTER_SANITIZE_STRING);
    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
    $start_date = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
    $end_date = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);

    // Get shift patterns
    $am_time_in = !empty($_POST['am_time_in']) ? $_POST['am_time_in'] : null;
    $am_time_out = !empty($_POST['am_time_out']) ? $_POST['am_time_out'] : null;
    $pm_time_in = !empty($_POST['pm_time_in']) ? $_POST['pm_time_in'] : null;
    $pm_time_out = !empty($_POST['pm_time_out']) ? $_POST['pm_time_out'] : null;

    // Get work days (if provided)
    $work_days = isset($_POST['work_days']) ? $_POST['work_days'] : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    // Validate required fields
    if (empty($employee_id) || empty($employee_name) || empty($start_date) || empty($end_date)) {
        $error_message = "Error: Please fill all required fields (Employee ID, Name, Start Date, and End Date).";
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
                    $day_of_week = $current_date->format('l');

                    // Check if it's a work day
                    if (in_array($day_of_week, $work_days)) {
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
                    }

                    $current_date->modify('+1 day');
                }

                $pdo->commit();

                if ($success_count > 0) {
                    $success_message = "Successfully added $success_count attendance records for $employee_name from $start_date to $end_date.";
                    if ($error_count > 0) {
                        $success_message .= " $error_count records were skipped (already exist or non-work days).";
                    }
                } else {
                    $error_message = "No records were added. All dates already have attendance records or are non-work days.";
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
                    header("Location: " . $_SERVER['PHP_SELF'] . "?status=add_success");
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
        <div class="p-4 md:p-6 bg-white rounded-lg shadow-md">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Attendance Management</h1>
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
                        <!-- NEW: Bulk Add Button -->
                        <button type="button" data-modal-target="bulkAddAttendanceModal" data-modal-toggle="bulkAddAttendanceModal"
                            class="text-white bg-yellow-600 hover:bg-yellow-700 focus:ring-4 focus:ring-yellow-300 font-medium rounded-lg text-sm px-4 py-2.5 transition duration-150 ease-in-out mobile-full flex items-center justify-center">
                            <i class="fas fa-calendar-plus mr-2"></i>
                            <span>Bulk Add (2 Weeks)</span>
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
                            </tr>
                            <tr>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th class="mobile-hidden"></th>
                                <th scope="col" class="px-3 py-2 text-center bg-blue-700 border-t border-blue-500 mobile-text-sm">In</th>
                                <th scope="col" class="px-3 py-2 text-center bg-blue-700 border-t border-blue-500 mobile-text-sm">Out</th>
                                <th scope="col" class="px-3 py-2 text-center bg-blue-700 border-t border-blue-500 mobile-text-sm">In</th>
                                <th scope"col" class="px-3 py-2 text-center bg-blue-700 border-t border-blue-500 mobile-text-sm">Out</th>
                                <th class="mobile-hidden"></th>
                                <th class="mobile-hidden"></th>
                                <th class="mobile-hidden"></th>
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
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan='11' class='text-center py-8 text-gray-500'>
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
                        <button data-modal-hide="addAttendanceModal" type="button" class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-4 py-2.5 hover:text-gray-900">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- NEW: Bulk Add Attendance Modal (2 Weeks) -->
    <div id="bulkAddAttendanceModal" tabindex="-1" aria-hidden="true" class="fixed top-0 left-0 right-0 z-50 hidden w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative w-full max-w-2xl max-h-full modal-animation modal-mobile-full">
            <div class="relative bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between p-5 border-b rounded-t bg-yellow-600 text-white">
                    <h3 class="text-lg md:text-xl font-semibold">
                        <i class="fas fa-calendar-plus mr-2"></i>Bulk Add Attendance (2 Weeks/Half Month)
                    </h3>
                    <button type="button" class="text-white bg-transparent hover:bg-yellow-700 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center transition duration-150 ease-in-out" data-modal-hide="bulkAddAttendanceModal">
                        <i class="fas fa-times w-5 h-5"></i>
                    </button>
                </div>
                <form action="" method="POST" class="p-4 md:p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="start_date" class="block mb-2 text-sm font-medium text-gray-900">Start Date *</label>
                            <input type="date" name="start_date" id="start_date"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-yellow-500 focus:border-yellow-500 block w-full p-2.5"
                                required
                                value="<?php
                                        $today = date('d');
                                        if ($today <= 15) {
                                            echo date('Y-m-01');
                                        } else {
                                            echo date('Y-m-16');
                                        }
                                        ?>">
                        </div>

                        <div>
                            <label for="end_date" class="block mb-2 text-sm font-medium text-gray-900">End Date *</label>
                            <input type="date" name="end_date" id="end_date"
                                class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-yellow-500 focus:border-yellow-500 block w-full p-2.5"
                                required
                                value="<?php
                                        $today = date('d');
                                        if ($today <= 15) {
                                            echo date('Y-m-15');
                                        } else {
                                            // Get last day of month
                                            echo date('Y-m-t');
                                        }
                                        ?>">
                        </div>
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

                    <!-- Work Days Selection -->
                    <div class="border-t pt-4">
                        <h6 class="text-base md:text-lg font-semibold text-yellow-600 mb-3 flex items-center">
                            <i class="fas fa-calendar-day mr-2"></i>Work Days (Select days to add attendance)
                        </h6>
                        <div class="flex flex-wrap gap-3">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="work_days[]" value="Monday" class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-500" checked>
                                <span class="ml-2 text-sm text-gray-700">Monday</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="work_days[]" value="Tuesday" class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-500" checked>
                                <span class="ml-2 text-sm text-gray-700">Tuesday</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="work_days[]" value="Wednesday" class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-500" checked>
                                <span class="ml-2 text-sm text-gray-700">Wednesday</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="work_days[]" value="Thursday" class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-500" checked>
                                <span class="ml-2 text-sm text-gray-700">Thursday</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="work_days[]" value="Friday" class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-500" checked>
                                <span class="ml-2 text-sm text-gray-700">Friday</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="work_days[]" value="Saturday" class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-500">
                                <span class="ml-2 text-sm text-gray-700">Saturday</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="work_days[]" value="Sunday" class="rounded border-gray-300 text-yellow-600 focus:ring-yellow-500">
                                <span class="ml-2 text-sm text-gray-700">Sunday</span>
                            </label>
                        </div>
                    </div>

                    <!-- Shift Times -->
                    <div class="border-t pt-4">
                        <h6 class="text-base md:text-lg font-semibold text-yellow-600 mb-3 flex items-center">
                            <i class="fas fa-clock mr-2"></i>Shift Times (Will be applied to all selected days)
                        </h6>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="bulk_am_time_in" class="block mb-2 text-sm font-medium text-gray-900">AM Time-in</label>
                                <input type="time" name="am_time_in" id="bulk_am_time_in"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-yellow-500 focus:border-yellow-500 block w-full p-2.5"
                                    value="08:00">
                            </div>
                            <div>
                                <label for="bulk_am_time_out" class="block mb-2 text-sm font-medium text-gray-900">AM Time-out</label>
                                <input type="time" name="am_time_out" id="bulk_am_time_out"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-yellow-500 focus:border-yellow-500 block w-full p-2.5"
                                    value="12:00">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="bulk_pm_time_in" class="block mb-2 text-sm font-medium text-gray-900">PM Time-in</label>
                                <input type="time" name="pm_time_in" id="bulk_pm_time_in"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-yellow-500 focus:border-yellow-500 block w-full p-2.5"
                                    value="13:00">
                            </div>
                            <div>
                                <label for="bulk_pm_time_out" class="block mb-2 text-sm font-medium text-gray-900">PM Time-out</label>
                                <input type="time" name="pm_time_out" id="bulk_pm_time_out"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-yellow-500 focus:border-yellow-500 block w-full p-2.5"
                                    value="17:00">
                            </div>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div class="border-t pt-4">
                        <h6 class="text-base md:text-lg font-semibold text-yellow-600 mb-3 flex items-center">
                            <i class="fas fa-eye mr-2"></i>Preview
                        </h6>
                        <div id="bulkPreview" class="p-3 bg-gray-50 rounded-lg text-sm text-gray-600">
                            <p>No preview available yet. Fill in the dates to see the days that will be added.</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end p-4 md:p-6 space-x-3 border-t border-gray-200 rounded-b">
                        <button type="submit" name="bulk_add_attendance" class="text-white bg-yellow-600 hover:bg-yellow-700 focus:ring-4 focus:outline-none focus:ring-yellow-300 font-medium rounded-lg text-sm px-4 py-2.5 text-center flex items-center">
                            <i class="fas fa-calendar-plus mr-2"></i>Add Bulk Attendance
                        </button>
                        <button data-modal-hide="bulkAddAttendanceModal" type="button" class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-4 py-2.5 hover:text-gray-900">
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
                <div class="flex items-center justify-between p-5 border-b rounded-t bg-blue-600 text-white">
                    <h3 class="text-lg md:text-xl font-semibold">
                        <i class="fas fa-file-import mr-2"></i>Import Attendance Records
                    </h3>
                    <button type="button" class="text-white bg-transparent hover:bg-blue-700 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center transition duration-150 ease-in-out" data-modal-hide="importAttendanceModal">
                        <i class="fas fa-times w-5 h-5"></i>
                    </button>
                </div>
                <form action="" method="POST" enctype="multipart/form-data" class="p-4 md:p-6 space-y-4">
                    <div class="space-y-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Download Template</label>
                            <a href="?download_template=true" id="downloadTemplate" class="text-white bg-green-600 hover:bg-green-700 focus:ring-4 focus:ring-green-300 font-medium rounded-lg text-sm px-4 py-2.5 inline-flex items-center">
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
                                    <input id="csv_file" name="csv_file" type="file" class="hidden" accept=".csv" required />
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

                        <div class="flex items-center p-4 text-sm text-blue-700 bg-blue-50 rounded-lg import-warning" role="alert">
                            <i class="fas fa-info-circle mr-2"></i>
                            <div>
                                <span class="font-medium">Note:</span>
                                <ul class="mt-1.5 ml-4 list-disc text-xs">
                                    <li>Time format: HH:MM (24-hour)</li>
                                    <li>Date format: YYYY-MM-DD</li>
                                    <li>Duplicate records will be skipped</li>
                                    <li>File size limit: 5MB</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end p-4 md:p-6 space-x-3 border-t border-gray-200 rounded-b">
                        <button type="submit" name="import_attendance" class="text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2.5 text-center flex items-center">
                            <i class="fas fa-upload mr-2"></i>Import Records
                        </button>
                        <button data-modal-hide="importAttendanceModal" type="button" class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 rounded-lg border border-gray-200 text-sm font-medium px-4 py-2.5 hover:text-gray-900">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.1.1/flowbite.min.js"></script>
    <script>
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

            // Employee auto-complete for add form
            const employees = <?php echo json_encode($all_employees); ?>;

            // Function to find employee by ID
            function findEmployeeById(id) {
                return employees.find(emp => emp.employee_id === id);
            }

            // Function to find employee by name
            function findEmployeeByName(name) {
                return employees.find(emp =>
                    emp.full_name.toLowerCase().includes(name.toLowerCase())
                );
            }

            // Add form auto-complete
            const employeeIdInput = document.getElementById('employee_id');
            const employeeNameInput = document.getElementById('employee_name');
            const departmentSelect = document.getElementById('department');

            if (employeeIdInput && employeeNameInput) {
                employeeIdInput.addEventListener('blur', function() {
                    const id = this.value.trim();
                    const employee = findEmployeeById(id);

                    if (employee) {
                        employeeNameInput.value = employee.full_name;
                        if (departmentSelect) {
                            departmentSelect.value = employee.department || '';
                        }
                    }
                });

                employeeNameInput.addEventListener('blur', function() {
                    const name = this.value.trim();
                    const employee = findEmployeeByName(name);

                    if (employee) {
                        employeeIdInput.value = employee.employee_id;
                        if (departmentSelect) {
                            departmentSelect.value = employee.department || '';
                        }
                    }
                });
            }

            // Bulk add form auto-complete
            const bulkEmployeeIdInput = document.getElementById('bulk_employee_id');
            const bulkEmployeeNameInput = document.getElementById('bulk_employee_name');
            const bulkDepartmentSelect = document.getElementById('bulk_department');

            if (bulkEmployeeIdInput && bulkEmployeeNameInput) {
                bulkEmployeeIdInput.addEventListener('blur', function() {
                    const id = this.value.trim();
                    const employee = findEmployeeById(id);

                    if (employee) {
                        bulkEmployeeNameInput.value = employee.full_name;
                        if (bulkDepartmentSelect) {
                            bulkDepartmentSelect.value = employee.department || '';
                        }
                    }
                });

                bulkEmployeeNameInput.addEventListener('blur', function() {
                    const name = this.value.trim();
                    const employee = findEmployeeByName(name);

                    if (employee) {
                        bulkEmployeeIdInput.value = employee.employee_id;
                        if (bulkDepartmentSelect) {
                            bulkDepartmentSelect.value = employee.department || '';
                        }
                    }
                });
            }

            // NEW: Bulk add date preview
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const workDayCheckboxes = document.querySelectorAll('input[name="work_days[]"]');
            const bulkPreview = document.getElementById('bulkPreview');

            function updateBulkPreview() {
                if (!startDateInput.value || !endDateInput.value) {
                    bulkPreview.innerHTML = '<p class="text-gray-600">Please select start and end dates.</p>';
                    return;
                }

                const start = new Date(startDateInput.value);
                const end = new Date(endDateInput.value);

                // Check if end date is after start date
                if (end < start) {
                    bulkPreview.innerHTML = '<p class="text-red-600">End date must be after start date.</p>';
                    return;
                }

                // Calculate days
                const daysCount = Math.floor((end - start) / (1000 * 60 * 60 * 24)) + 1;

                if (daysCount > 31) {
                    bulkPreview.innerHTML = `<p class="text-red-600">Maximum period is 31 days (you selected ${daysCount} days).</p>`;
                    return;
                }

                // Get selected work days
                const selectedDays = Array.from(workDayCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);

                // Generate preview
                let previewHTML = `<p class="font-medium text-gray-700 mb-2">Will add attendance for ${daysCount} days (${selectedDays.join(', ')})</p>`;
                previewHTML += '<div class="max-h-40 overflow-y-auto">';

                const current = new Date(start);
                let workDaysCount = 0;

                for (let i = 0; i < daysCount; i++) {
                    const dayName = current.toLocaleDateString('en-US', {
                        weekday: 'long'
                    });
                    const dateStr = current.toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    });

                    if (selectedDays.includes(dayName)) {
                        workDaysCount++;
                        previewHTML += `<div class="flex items-center py-1 border-b border-gray-100">
                            <i class="fas fa-calendar-day text-green-500 mr-2 text-xs"></i>
                            <span class="text-sm">${dateStr} (${dayName})</span>
                        </div>`;
                    } else {
                        previewHTML += `<div class="flex items-center py-1 border-b border-gray-100 text-gray-400">
                            <i class="fas fa-calendar-times text-gray-300 mr-2 text-xs"></i>
                            <span class="text-sm">${dateStr} (${dayName}) - <span class="text-xs italic">skipped</span></span>
                        </div>`;
                    }

                    current.setDate(current.getDate() + 1);
                }

                previewHTML += '</div>';
                previewHTML += `<p class="mt-2 text-sm font-medium text-green-600">Total work days to add: ${workDaysCount}</p>`;

                bulkPreview.innerHTML = previewHTML;
            }

            // Add event listeners for bulk preview
            if (startDateInput) startDateInput.addEventListener('change', updateBulkPreview);
            if (endDateInput) endDateInput.addEventListener('change', updateBulkPreview);
            if (workDayCheckboxes.length > 0) {
                workDayCheckboxes.forEach(cb => {
                    cb.addEventListener('change', updateBulkPreview);
                });
            }

            // Initial preview
            updateBulkPreview();

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
        });
    </script>
</body>

</html>
<?php
// Close PDO connection
$pdo = null;
?>