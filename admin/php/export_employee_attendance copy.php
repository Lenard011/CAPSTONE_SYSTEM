<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Include PHPExcel library if available, otherwise use CSV
// For Excel export, you would need to install PhpSpreadsheet:
// composer require phpoffice/phpspreadsheet

/**
 * Alternative: Simple HTML Excel Export (works without external libraries)
 */

// =================================================================================
// --- Database Configuration ---
// =================================================================================

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

// =================================================================================
// --- Get Employee Data ---
// =================================================================================

$employee_id = isset($_POST['employee_id']) ? $_POST['employee_id'] : (isset($_GET['employee_id']) ? $_GET['employee_id'] : '');
$export_format = isset($_GET['format']) ? $_GET['format'] : 'csv'; // csv or excel

if (empty($employee_id)) {
    die("Error: Employee ID is required.");
}

// =================================================================================
// --- Fetch Employee Details ---
// =================================================================================

function getEmployeeById($pdo, $employee_id)
{
    $tables = [
        ['sql' => "SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name, office as department FROM permanent WHERE employee_id = ? AND status = 'Active'", 'type' => 'Permanent'],
        ['sql' => "SELECT employee_id, employee_name as full_name, office as department FROM job_order WHERE employee_id = ? AND is_archived = 0", 'type' => 'Job Order'],
        ['sql' => "SELECT employee_id, full_name, office_assignment as department FROM contractofservice WHERE employee_id = ? AND status = 'active'", 'type' => 'Contractual']
    ];

    foreach ($tables as $table) {
        $stmt = $pdo->prepare($table['sql']);
        $stmt->execute([$employee_id]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['type'] = $table['type'];
            return $row;
        }
    }
    return null;
}

$employee = getEmployeeById($pdo, $employee_id);
if (!$employee) {
    die("Error: Employee not found.");
}

// =================================================================================
// --- Fetch Attendance Records ---
// =================================================================================

try {
    $sql = "SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$employee_id]);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($attendance_records)) {
        die("Error: No attendance records found for this employee.");
    }
} catch (PDOException $e) {
    die("Error: Could not retrieve attendance records.");
}

// =================================================================================
// --- Calculate Statistics ---
// =================================================================================

$stats = [
    'total_days' => count($attendance_records),
    'present_days' => 0,
    'absent_days' => 0,
    'late_days' => 0,
    'total_hours' => 0,
    'total_ot' => 0,
    'total_undertime' => 0
];

foreach ($attendance_records as $record) {
    if ($record['total_hours'] > 0) $stats['present_days']++;
    if ($record['total_hours'] == 0) $stats['absent_days']++;
    if ($record['under_time'] > 0) $stats['late_days']++;
    $stats['total_hours'] += $record['total_hours'];
    $stats['total_ot'] += $record['ot_hours'];
    $stats['total_undertime'] += $record['under_time'];
}

$stats['present_rate'] = $stats['total_days'] > 0 ? round(($stats['present_days'] / $stats['total_days']) * 100, 1) : 0;

// =================================================================================
// --- Export to Excel (HTML format) ---
// =================================================================================

if ($export_format === 'excel') {
    $filename = "attendance_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $employee['full_name']) . "_" . date('Y-m-d') . ".xls";

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #000; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { background-color: #4CAF50; color: white; font-size: 18px; }
            .summary { background-color: #e8f4f8; }
            .total { background-color: #d9edf7; font-weight: bold; }
        </style>
    </head>
    <body>';

    // Header
    echo '<table>
        <tr><td colspan="11" class="header" style="text-align: center; font-size: 20px; padding: 15px;">EMPLOYEE ATTENDANCE REPORT</td></tr>
        <tr><td colspan="11" style="padding: 10px;"></td></tr>
        
        <tr><th colspan="2" style="background-color: #2196F3; color: white;">Employee Information</th><td colspan="9"></td></tr>
        <tr><th>Employee ID:</th><td>' . htmlspecialchars($employee['employee_id']) . '</td><td colspan="9"></td></tr>
        <tr><th>Name:</th><td>' . htmlspecialchars($employee['full_name']) . '</td><td colspan="9"></td></tr>
        <tr><th>Department:</th><td>' . htmlspecialchars($employee['department']) . '</td><td colspan="9"></td></tr>
        <tr><th>Employee Type:</th><td>' . htmlspecialchars($employee['type']) . '</td><td colspan="9"></td></tr>
        <tr><th>Report Generated:</th><td>' . date('F j, Y g:i A') . '</td><td colspan="9"></td></tr>
        <tr><td colspan="11" style="padding: 10px;"></td></tr>
        
        <tr><th colspan="11" style="background-color: #2196F3; color: white;">Attendance Summary</th></tr>
        <tr class="summary">
            <th>Total Days</th><td>' . $stats['total_days'] . '</td>
            <th>Present Days</th><td>' . $stats['present_days'] . ' (' . $stats['present_rate'] . '%)</td>
            <th>Absent Days</th><td>' . $stats['absent_days'] . '</td>
            <th>Late Days</th><td>' . $stats['late_days'] . '</td>
            <th>Total Hours</th><td>' . round($stats['total_hours'], 2) . '</td>
            <td></td>
        </tr>
        <tr class="summary">
            <th>Total OT Hours</th><td>' . round($stats['total_ot'], 2) . '</td>
            <th>Total Undertime</th><td>' . round($stats['total_undertime'], 2) . '</td>
            <td colspan="7"></td>
        </tr>
        <tr><td colspan="11" style="padding: 10px;"></td></tr>
        
        <tr><th colspan="11" style="background-color: #2196F3; color: white;">Detailed Attendance Records</th></tr>
        <tr>
            <th>Date</th>
            <th>Day</th>
            <th>AM In</th>
            <th>AM Out</th>
            <th>PM In</th>
            <th>PM Out</th>
            <th>OT Hours</th>
            <th>Undertime</th>
            <th>Total Hours</th>
            <th>Status</th>
            <th>Remarks</th>
        </tr>';

    // Data rows
    foreach ($attendance_records as $record) {
        $date = date('Y-m-d', strtotime($record['date']));
        $day = date('D', strtotime($record['date']));
        $is_weekend = (date('N', strtotime($record['date'])) >= 6);

        // Format times
        $am_in = !empty($record['am_time_in']) ? date('h:i A', strtotime($record['am_time_in'])) : '--';
        $am_out = !empty($record['am_time_out']) ? date('h:i A', strtotime($record['am_time_out'])) : '--';
        $pm_in = !empty($record['pm_time_in']) ? date('h:i A', strtotime($record['pm_time_in'])) : '--';
        $pm_out = !empty($record['pm_time_out']) ? date('h:i A', strtotime($record['pm_time_out'])) : '--';

        // Determine status
        $status = 'Present';
        $remarks = '';

        if ($record['total_hours'] == 0) {
            $status = 'Absent';
            $remarks = 'No time recorded';
        } elseif ($record['under_time'] > 0) {
            $status = 'Late';
            $remarks = round($record['under_time'], 2) . 'h undertime';
        } elseif ($record['ot_hours'] > 0) {
            $status = 'Present with OT';
            $remarks = round($record['ot_hours'], 2) . 'h OT';
        }

        if ($is_weekend && $record['total_hours'] > 0) {
            $status = 'Weekend Work';
            $remarks = 'Worked on weekend';
        }

        // Color coding for status
        $status_color = '';
        switch ($status) {
            case 'Present':
                $status_color = 'background-color: #d4edda; color: #155724;';
                break;
            case 'Absent':
                $status_color = 'background-color: #f8d7da; color: #721c24;';
                break;
            case 'Late':
                $status_color = 'background-color: #fff3cd; color: #856404;';
                break;
            case 'Present with OT':
                $status_color = 'background-color: #d1ecf1; color: #0c5460;';
                break;
            case 'Weekend Work':
                $status_color = 'background-color: #e2e3e5; color: #383d41;';
                break;
        }

        echo '<tr>
            <td>' . $date . '</td>
            <td>' . $day . '</td>
            <td>' . $am_in . '</td>
            <td>' . $am_out . '</td>
            <td>' . $pm_in . '</td>
            <td>' . $pm_out . '</td>
            <td>' . round($record['ot_hours'], 2) . '</td>
            <td>' . round($record['under_time'], 2) . '</td>
            <td>' . round($record['total_hours'], 2) . '</td>
            <td style="' . $status_color . '">' . $status . '</td>
            <td>' . $remarks . '</td>
        </tr>';
    }

    // Monthly summary if we have data
    if (!empty($attendance_records)) {
        $monthly_totals = [];

        foreach ($attendance_records as $record) {
            $month = date('F Y', strtotime($record['date']));

            if (!isset($monthly_totals[$month])) {
                $monthly_totals[$month] = ['days' => 0, 'hours' => 0, 'ot' => 0, 'undertime' => 0];
            }

            $monthly_totals[$month]['days']++;
            $monthly_totals[$month]['hours'] += $record['total_hours'];
            $monthly_totals[$month]['ot'] += $record['ot_hours'];
            $monthly_totals[$month]['undertime'] += $record['under_time'];
        }

        echo '<tr><td colspan="11" style="padding: 15px;"></td></tr>
        <tr><th colspan="11" style="background-color: #2196F3; color: white;">Monthly Breakdown</th></tr>
        <tr>
            <th>Month</th>
            <th>Days</th>
            <th>Total Hours</th>
            <th>OT Hours</th>
            <th>Undertime Hours</th>
            <th colspan="6"></th>
        </tr>';

        foreach ($monthly_totals as $month => $totals) {
            echo '<tr>
                <td>' . $month . '</td>
                <td>' . $totals['days'] . '</td>
                <td>' . round($totals['hours'], 2) . '</td>
                <td>' . round($totals['ot'], 2) . '</td>
                <td>' . round($totals['undertime'], 2) . '</td>
                <td colspan="6"></td>
            </tr>';
        }
    }

    // Footer
    echo '<tr><td colspan="11" style="padding: 15px;"></td></tr>
        <tr><td colspan="11" style="text-align: center; font-style: italic; padding: 10px;">
            Generated by HRMS - Paluan Occidental Mindoro<br>
            © ' . date('Y') . ' Paluan LGU. All rights reserved.
        </td></tr>
    </table>
    </body>
    </html>';

    exit();
}

// =================================================================================
// --- Default CSV Export (fallback) ---
// =================================================================================

$filename = "attendance_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $employee['full_name']) . "_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Write data
fputcsv($output, ['EMPLOYEE ATTENDANCE REPORT']);
fputcsv($output, ['']);
fputcsv($output, ['Employee Information:']);
fputcsv($output, ['Employee ID:', $employee['employee_id']]);
fputcsv($output, ['Name:', $employee['full_name']]);
fputcsv($output, ['Department:', $employee['department']]);
fputcsv($output, ['Employee Type:', $employee['type']]);
fputcsv($output, ['']);
fputcsv($output, ['Attendance Summary:']);
fputcsv($output, ['Total Days:', $stats['total_days']]);
fputcsv($output, ['Present Days:', $stats['present_days'] . ' (' . $stats['present_rate'] . '%)']);
fputcsv($output, ['Absent Days:', $stats['absent_days']]);
fputcsv($output, ['Late Days:', $stats['late_days']]);
fputcsv($output, ['Total Hours:', round($stats['total_hours'], 2)]);
fputcsv($output, ['Total OT Hours:', round($stats['total_ot'], 2)]);
fputcsv($output, ['Total Undertime:', round($stats['total_undertime'], 2)]);
fputcsv($output, ['']);
fputcsv($output, ['Detailed Records:']);
fputcsv($output, ['Date', 'Day', 'AM In', 'AM Out', 'PM In', 'PM Out', 'OT Hours', 'Undertime', 'Total Hours', 'Status', 'Remarks']);

foreach ($attendance_records as $record) {
    $date = date('Y-m-d', strtotime($record['date']));
    $day = date('D', strtotime($record['date']));
    $is_weekend = (date('N', strtotime($record['date'])) >= 6);

    $am_in = !empty($record['am_time_in']) ? date('h:i A', strtotime($record['am_time_in'])) : '';
    $am_out = !empty($record['am_time_out']) ? date('h:i A', strtotime($record['am_time_out'])) : '';
    $pm_in = !empty($record['pm_time_in']) ? date('h:i A', strtotime($record['pm_time_in'])) : '';
    $pm_out = !empty($record['pm_time_out']) ? date('h:i A', strtotime($record['pm_time_out'])) : '';

    $status = 'Present';
    $remarks = '';

    if ($record['total_hours'] == 0) {
        $status = 'Absent';
        $remarks = 'No time recorded';
    } elseif ($record['under_time'] > 0) {
        $status = 'Late';
        $remarks = round($record['under_time'], 2) . 'h undertime';
    } elseif ($record['ot_hours'] > 0) {
        $status = 'Present with OT';
        $remarks = round($record['ot_hours'], 2) . 'h OT';
    }

    if ($is_weekend && $record['total_hours'] > 0) {
        $status = 'Weekend Work';
        $remarks = 'Worked on weekend';
    }

    fputcsv($output, [
        $date,
        $day,
        $am_in,
        $am_out,
        $pm_in,
        $pm_out,
        round($record['ot_hours'], 2),
        round($record['under_time'], 2),
        round($record['total_hours'], 2),
        $status,
        $remarks
    ]);
}

fclose($output);
$pdo = null;
exit();
