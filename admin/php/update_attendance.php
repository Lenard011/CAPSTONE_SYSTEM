<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Database Configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'hrms_paluan');

try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("ERROR: Could not connect to the database.");
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
    $standard_end_pm = strtotime('17:00:00');

    if ($am_in && $am_out) {
        $am_in_time = strtotime($am_in);
        $am_out_time = strtotime($am_out);

        if ($am_in_time && $am_out_time && $am_out_time > $am_in_time) {
            $am_worked = ($am_out_time - $am_in_time) / 60;

            if ($am_in_time < $standard_start_am) {
                $ot_minutes += ($standard_start_am - $am_in_time) / 60;
            }

            if ($am_in_time > $standard_start_am) {
                $undertime_minutes += ($am_in_time - $standard_start_am) / 60;
            }

            $total_minutes += $am_worked;
        }
    }

    if ($pm_in && $pm_out) {
        $pm_in_time = strtotime($pm_in);
        $pm_out_time = strtotime($pm_out);

        if ($pm_in_time && $pm_out_time && $pm_out_time > $pm_in_time) {
            $pm_worked = ($pm_out_time - $pm_in_time) / 60;

            if ($pm_out_time > $standard_end_pm) {
                $ot_minutes += ($pm_out_time - $standard_end_pm) / 60;
            }

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
// --- Update Attendance Record ---
// =================================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendance_id = isset($_POST['attendance_id']) ? intval($_POST['attendance_id']) : 0;
    $date = isset($_POST['date']) ? $_POST['date'] : '';
    $am_time_in = !empty($_POST['am_time_in']) ? $_POST['am_time_in'] : null;
    $am_time_out = !empty($_POST['am_time_out']) ? $_POST['am_time_out'] : null;
    $pm_time_in = !empty($_POST['pm_time_in']) ? $_POST['pm_time_in'] : null;
    $pm_time_out = !empty($_POST['pm_time_out']) ? $_POST['pm_time_out'] : null;
    $redirect_params = isset($_POST['redirect_params']) ? $_POST['redirect_params'] : '';

    if ($attendance_id <= 0 || empty($date)) {
        $error_message = "Error: Invalid attendance record.";
        $redirect_url = 'attendance.php' . $redirect_params;
        if (strpos($redirect_url, '?') === false) {
            $redirect_url .= '?status=edit_error&message=' . urlencode($error_message);
        } else {
            $redirect_url .= '&status=edit_error&message=' . urlencode($error_message);
        }
        header("Location: " . $redirect_url);
        exit();
    }

    try {
        // Get current employee_id to check for duplicates (excluding this record)
        $get_sql = "SELECT employee_id FROM attendance WHERE id = ?";
        $get_stmt = $pdo->prepare($get_sql);
        $get_stmt->execute([$attendance_id]);
        $record = $get_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            $error_message = "Error: Attendance record not found.";
            $redirect_url = 'attendance.php' . $redirect_params;
            if (strpos($redirect_url, '?') === false) {
                $redirect_url .= '?status=edit_error&message=' . urlencode($error_message);
            } else {
                $redirect_url .= '&status=edit_error&message=' . urlencode($error_message);
            }
            header("Location: " . $redirect_url);
            exit();
        }

        $employee_id = $record['employee_id'];

        // FIXED: Check for duplicates (exclude current record)
        $check_sql = "SELECT id FROM attendance WHERE employee_id = ? AND date = ? AND id != ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$employee_id, $date, $attendance_id]);

        if ($check_stmt->rowCount() > 0) {
            $error_message = "Error: Another attendance record already exists for this employee on the selected date.";
            $redirect_url = 'attendance.php' . $redirect_params;
            if (strpos($redirect_url, '?') === false) {
                $redirect_url .= '?status=edit_error&message=' . urlencode($error_message);
            } else {
                $redirect_url .= '&status=edit_error&message=' . urlencode($error_message);
            }
            header("Location: " . $redirect_url);
            exit();
        }

        // Calculate hours
        $hours = calculateWorkingHours($am_time_in, $am_time_out, $pm_time_in, $pm_time_out);

        // Update record
        $sql = "UPDATE attendance 
                SET date = ?, 
                    am_time_in = ?, 
                    am_time_out = ?, 
                    pm_time_in = ?, 
                    pm_time_out = ?,
                    ot_hours = ?,
                    under_time = ?,
                    total_hours = ?
                WHERE id = ?";

        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([
            $date,
            $am_time_in,
            $am_time_out,
            $pm_time_in,
            $pm_time_out,
            $hours['ot_hours'],
            $hours['undertime_hours'],
            $hours['total_hours'],
            $attendance_id
        ])) {
            $redirect_url = 'attendance.php' . $redirect_params;
            if (strpos($redirect_url, '?') === false) {
                $redirect_url .= '?status=edit_success';
            } else {
                $redirect_url .= '&status=edit_success';
            }
            header("Location: " . $redirect_url);
            exit();
        } else {
            $error_message = "Error: Failed to update attendance record.";
            $redirect_url = 'attendance.php' . $redirect_params;
            if (strpos($redirect_url, '?') === false) {
                $redirect_url .= '?status=edit_error&message=' . urlencode($error_message);
            } else {
                $redirect_url .= '&status=edit_error&message=' . urlencode($error_message);
            }
            header("Location: " . $redirect_url);
            exit();
        }
    } catch (PDOException $e) {
        error_log("Update attendance error: " . $e->getMessage());
        $error_message = "Database error: Failed to update attendance record.";
        $redirect_url = 'attendance.php' . $redirect_params;
        if (strpos($redirect_url, '?') === false) {
            $redirect_url .= '?status=edit_error&message=' . urlencode($error_message);
        } else {
            $redirect_url .= '&status=edit_error&message=' . urlencode($error_message);
        }
        header("Location: " . $redirect_url);
        exit();
    }
} else {
    header('Location: attendance.php');
    exit();
}
