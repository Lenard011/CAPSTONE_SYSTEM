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
    die("ERROR: Could not connect to the database.");
}

// Update attendance record
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['attendance_id'])) {
    $attendance_id = (int)$_POST['attendance_id'];
    $date = $_POST['date'] ?? '';
    $am_time_in = !empty($_POST['am_time_in']) ? $_POST['am_time_in'] : null;
    $am_time_out = !empty($_POST['am_time_out']) ? $_POST['am_time_out'] : null;
    $pm_time_in = !empty($_POST['pm_time_in']) ? $_POST['pm_time_in'] : null;
    $pm_time_out = !empty($_POST['pm_time_out']) ? $_POST['pm_time_out'] : null;

    // Get redirect parameters
    $redirect_params = isset($_POST['redirect_params']) ? $_POST['redirect_params'] : '?status=edit_success';

    // Calculate working hours
    function calculateWorkingHours($am_in, $am_out, $pm_in, $pm_out)
    {
        $total_minutes = 0;
        $ot_minutes = 0;
        $undertime_minutes = 0;

        $standard_start_am = strtotime('08:00:00');
        $standard_end_am = strtotime('12:00:00');
        $standard_start_pm = strtotime('13:00:00');
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

        $total_hours = round($total_minutes / 60, 2);
        $ot_hours = round($ot_minutes / 60, 2);
        $undertime_hours = round($undertime_minutes / 60, 2);

        return [
            'total_hours' => max(0, $total_hours),
            'ot_hours' => max(0, $ot_hours),
            'undertime_hours' => max(0, $undertime_hours)
        ];
    }

    $hours = calculateWorkingHours($am_time_in, $am_time_out, $pm_time_in, $pm_time_out);

    try {
        $sql = "UPDATE attendance SET 
                date = ?, 
                am_time_in = ?, 
                am_time_out = ?, 
                pm_time_in = ?, 
                pm_time_out = ?, 
                ot_hours = ?, 
                under_time = ?, 
                total_hours = ? 
                WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $date,
            $am_time_in,
            $am_time_out,
            $pm_time_in,
            $pm_time_out,
            $hours['ot_hours'],
            $hours['undertime_hours'],
            $hours['total_hours'],
            $attendance_id
        ]);

        if ($success) {
            header('Location: attendance.php' . $redirect_params);
        } else {
            header('Location: attendance.php?status=edit_error');
        }
        exit();
    } catch (PDOException $e) {
        error_log("Update attendance error: " . $e->getMessage());
        header('Location: attendance.php?status=edit_error');
        exit();
    }
} else {
    header('Location: attendance.php');
    exit();
}

$pdo = null;
