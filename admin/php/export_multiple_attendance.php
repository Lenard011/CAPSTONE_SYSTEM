<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// =================================================================================
// --- PhpSpreadsheet Autoloader ---
// =================================================================================

$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];

$loaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    die('PhpSpreadsheet library not installed. Please run: composer require phpoffice/phpspreadsheet');
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'hrms_paluan');

// =================================================================================
// --- Database Connection ---
// =================================================================================

try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");
} catch (PDOException $e) {
    die("Database connection error.");
}

// =================================================================================
// --- Get Parameters ---
// =================================================================================

$employee_ids = isset($_GET['employee_ids']) ? $_GET['employee_ids'] : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$department = isset($_GET['department']) ? $_GET['department'] : '';
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';
$include_summary = isset($_GET['include_summary']) && $_GET['include_summary'] == '1';
$include_employee_info = isset($_GET['include_employee_info']) && $_GET['include_employee_info'] == '1';
$format_time_12h = isset($_GET['format_time_12h']) && $_GET['format_time_12h'] == '1';

if (empty($employee_ids)) {
    die("No employees selected for export");
}

// Parse employee IDs
$employee_id_array = array_map('trim', explode(',', $employee_ids));
$employee_id_array = array_filter($employee_id_array); // Remove empty values

if (empty($employee_id_array)) {
    die("No valid employees selected for export");
}

// Build placeholders for IN clause
$placeholders = implode(',', array_fill(0, count($employee_id_array), '?'));

// =================================================================================
// --- Build Query ---
// =================================================================================

$sql = "SELECT * FROM attendance WHERE employee_id IN ($placeholders)";
$params = $employee_id_array;

if (!empty($from_date)) {
    $sql .= " AND date >= ?";
    $params[] = $from_date;
}

if (!empty($to_date)) {
    $sql .= " AND date <= ?";
    $params[] = $to_date;
}

if (!empty($department)) {
    $sql .= " AND department = ?";
    $params[] = $department;
}

$sql .= " ORDER BY employee_name ASC, date ASC";

// Execute query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($records)) {
    die("No attendance records found for the selected criteria");
}

// =================================================================================
// --- Group records by employee ---
// =================================================================================

$employees_data = [];
foreach ($records as $record) {
    $emp_id = $record['employee_id'];
    if (!isset($employees_data[$emp_id])) {
        $employees_data[$emp_id] = [
            'employee_id' => $emp_id,
            'employee_name' => $record['employee_name'],
            'department' => $record['department'],
            'records' => []
        ];
    }
    $employees_data[$emp_id]['records'][] = $record;
}

// =================================================================================
// --- Handle different export formats ---
// =================================================================================

if ($format === 'csv') {
    // CSV Export - Combined format
    $filename = 'attendance_export_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $output = fopen('php://output', 'w');
    fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

    // Header
    fputcsv($output, ['Employee ID', 'Employee Name', 'Department', 'Date', 'Day', 'AM In', 'AM Out', 'PM In', 'PM Out', 'OT Hours', 'Undertime', 'Total Hours']);

    // Data
    foreach ($records as $record) {
        $time_format = $format_time_12h ? 'h:i A' : 'H:i';

        $row = [
            $record['employee_id'],
            $record['employee_name'],
            $record['department'],
            $record['date'],
            date('D', strtotime($record['date'])),
            !empty($record['am_time_in']) ? ($format_time_12h ? date('h:i A', strtotime($record['am_time_in'])) : substr($record['am_time_in'], 0, 5)) : '',
            !empty($record['am_time_out']) ? ($format_time_12h ? date('h:i A', strtotime($record['am_time_out'])) : substr($record['am_time_out'], 0, 5)) : '',
            !empty($record['pm_time_in']) ? ($format_time_12h ? date('h:i A', strtotime($record['pm_time_in'])) : substr($record['pm_time_in'], 0, 5)) : '',
            !empty($record['pm_time_out']) ? ($format_time_12h ? date('h:i A', strtotime($record['pm_time_out'])) : substr($record['pm_time_out'], 0, 5)) : '',
            $record['ot_hours'],
            $record['under_time'],
            $record['total_hours']
        ];
        fputcsv($output, $row);
    }

    // Add summary row if requested
    if ($include_summary) {
        $total_hours_sum = array_sum(array_column($records, 'total_hours'));
        $total_ot_sum = array_sum(array_column($records, 'ot_hours'));
        $total_undertime_sum = array_sum(array_column($records, 'under_time'));

        fputcsv($output, []); // Empty row
        fputcsv($output, ['TOTALS:', '', '', '', '', '', '', '', '', round($total_ot_sum, 2), round($total_undertime_sum, 2), round($total_hours_sum, 2)]);
    }

    fclose($output);
    exit();
} elseif ($format === 'excel') {
    // Excel Export - Separate sheets per employee
    $spreadsheet = new Spreadsheet();

    // Remove default sheet
    $spreadsheet->removeSheetByIndex(0);

    $sheet_index = 0;

    foreach ($employees_data as $emp_id => $employee) {
        // Create a new sheet for each employee
        $sheet = $spreadsheet->createSheet($sheet_index);

        // Set sheet title (max 31 characters, remove invalid characters)
        $sheet_title = substr(preg_replace('/[^\w\s-]/', '', $employee['employee_name']), 0, 25);
        if (empty($sheet_title)) {
            $sheet_title = "Employee_" . $emp_id;
        }
        $sheet->setTitle($sheet_title);

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(15); // Date
        $sheet->getColumnDimension('B')->setWidth(12); // Day
        $sheet->getColumnDimension('C')->setWidth(15); // AM In
        $sheet->getColumnDimension('D')->setWidth(15); // AM Out
        $sheet->getColumnDimension('E')->setWidth(15); // PM In
        $sheet->getColumnDimension('F')->setWidth(15); // PM Out
        $sheet->getColumnDimension('G')->setWidth(12); // OT Hours
        $sheet->getColumnDimension('H')->setWidth(12); // Undertime
        $sheet->getColumnDimension('I')->setWidth(12); // Total Hours

        $current_row = 1;

        // Title Header
        $sheet->setCellValue('A' . $current_row, 'ATTENDANCE RECORDS');
        $sheet->mergeCells('A' . $current_row . ':I' . $current_row);
        $sheet->getStyle('A' . $current_row)->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A' . $current_row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $current_row += 2;

        // Employee Information
        if ($include_employee_info) {
            $sheet->setCellValue('A' . $current_row, 'Employee ID:');
            $sheet->setCellValue('B' . $current_row, $employee['employee_id']);
            $sheet->getStyle('A' . $current_row)->getFont()->setBold(true);
            $current_row++;

            $sheet->setCellValue('A' . $current_row, 'Employee Name:');
            $sheet->setCellValue('B' . $current_row, $employee['employee_name']);
            $sheet->getStyle('A' . $current_row)->getFont()->setBold(true);
            $current_row++;

            $sheet->setCellValue('A' . $current_row, 'Department:');
            $sheet->setCellValue('B' . $current_row, $employee['department']);
            $sheet->getStyle('A' . $current_row)->getFont()->setBold(true);
            $current_row++;

            // Date range
            if (!empty($employee['records'])) {
                $dates = array_column($employee['records'], 'date');
                $earliest_date = min($dates);
                $latest_date = max($dates);
                $sheet->setCellValue('A' . $current_row, 'Date Range:');
                $sheet->setCellValue('B' . $current_row, date('M d, Y', strtotime($earliest_date)) . ' to ' . date('M d, Y', strtotime($latest_date)));
                $sheet->getStyle('A' . $current_row)->getFont()->setBold(true);
            }
            $current_row += 2;
        } else {
            $current_row++;
        }

        // Headers
        $headers = ['Date', 'Day', 'AM In', 'AM Out', 'PM In', 'PM Out', 'OT (h)', 'Undertime (h)', 'Total (h)'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $current_row, $header);
            $col++;
        }

        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        $sheet->getStyle('A' . $current_row . ':I' . $current_row)->applyFromArray($headerStyle);
        $sheet->getRowDimension($current_row)->setRowHeight(20);

        $data_start_row = $current_row;
        $current_row++;

        // Fill Data
        $total_hours_sum = 0;
        $total_ot_sum = 0;
        $total_undertime_sum = 0;

        foreach ($employee['records'] as $record) {
            $date = date('Y-m-d', strtotime($record['date']));
            $day_name = date('D', strtotime($record['date']));

            // Format times
            if ($format_time_12h) {
                $am_in = !empty($record['am_time_in']) ? date('h:i A', strtotime($record['am_time_in'])) : '--';
                $am_out = !empty($record['am_time_out']) ? date('h:i A', strtotime($record['am_time_out'])) : '--';
                $pm_in = !empty($record['pm_time_in']) ? date('h:i A', strtotime($record['pm_time_in'])) : '--';
                $pm_out = !empty($record['pm_time_out']) ? date('h:i A', strtotime($record['pm_time_out'])) : '--';
            } else {
                $am_in = !empty($record['am_time_in']) ? substr($record['am_time_in'], 0, 5) : '--';
                $am_out = !empty($record['am_time_out']) ? substr($record['am_time_out'], 0, 5) : '--';
                $pm_in = !empty($record['pm_time_in']) ? substr($record['pm_time_in'], 0, 5) : '--';
                $pm_out = !empty($record['pm_time_out']) ? substr($record['pm_time_out'], 0, 5) : '--';
            }

            $sheet->setCellValue('A' . $current_row, $date);
            $sheet->setCellValue('B' . $current_row, $day_name);
            $sheet->setCellValue('C' . $current_row, $am_in);
            $sheet->setCellValue('D' . $current_row, $am_out);
            $sheet->setCellValue('E' . $current_row, $pm_in);
            $sheet->setCellValue('F' . $current_row, $pm_out);
            $sheet->setCellValue('G' . $current_row, $record['ot_hours']);
            $sheet->setCellValue('H' . $current_row, $record['under_time']);
            $sheet->setCellValue('I' . $current_row, $record['total_hours']);

            // Color-code weekends
            if ($day_name == 'Sat' || $day_name == 'Sun') {
                $sheet->getStyle('A' . $current_row . ':I' . $current_row)
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFF3E0'); // Light orange for weekends
            }

            $total_hours_sum += $record['total_hours'];
            $total_ot_sum += $record['ot_hours'];
            $total_undertime_sum += $record['under_time'];

            $current_row++;
        }

        $data_end_row = $current_row - 1;

        // Summary Row (if enabled)
        if ($include_summary) {
            $summary_row = $current_row + 1;

            $sheet->setCellValue('A' . $summary_row, 'TOTALS:');
            $sheet->mergeCells('A' . $summary_row . ':F' . $summary_row);
            $sheet->setCellValue('G' . $summary_row, round($total_ot_sum, 2));
            $sheet->setCellValue('H' . $summary_row, round($total_undertime_sum, 2));
            $sheet->setCellValue('I' . $summary_row, round($total_hours_sum, 2));

            $sheet->getStyle('A' . $summary_row . ':I' . $summary_row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $summary_row . ':I' . $summary_row)->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
            $sheet->getStyle('A' . $summary_row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Add footer
            $footer_row = $summary_row + 2;
            $sheet->setCellValue('A' . $footer_row, 'Generated on: ' . date('F d, Y h:i A'));
            $sheet->mergeCells('A' . $footer_row . ':I' . $footer_row);
            $sheet->getStyle('A' . $footer_row)->getFont()->setItalic(true);
        } else {
            // Add footer without summary
            $footer_row = $current_row + 1;
            $sheet->setCellValue('A' . $footer_row, 'Generated on: ' . date('F d, Y h:i A'));
            $sheet->mergeCells('A' . $footer_row . ':I' . $footer_row);
            $sheet->getStyle('A' . $footer_row)->getFont()->setItalic(true);
        }

        // Format borders for data rows
        if ($data_end_row >= $data_start_row) {
            $sheet->getStyle('A' . $data_start_row . ':I' . $data_end_row)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        // Center align all data
        $sheet->getStyle('A' . $data_start_row . ':I' . $data_end_row)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Make date column left-aligned
        $sheet->getStyle('A' . $data_start_row . ':A' . $data_end_row)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $sheet_index++;
    }

    // Set first sheet as active
    if ($sheet_index > 0) {
        $spreadsheet->setActiveSheetIndex(0);
    }

    // Generate Filename
    $filename = 'attendance_export_';
    if (!empty($from_date) && !empty($to_date)) {
        $filename .= date('Ymd', strtotime($from_date)) . '_' . date('Ymd', strtotime($to_date));
    } else {
        $filename .= date('Ymd');
    }
    $filename .= '_' . count($employees_data) . '_employees.xlsx';

    // Output File
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

// If we get here, something went wrong
die("Invalid export format specified.");
