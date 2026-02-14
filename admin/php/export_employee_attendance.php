<?php
session_start();
// =================================================================================
// --- Get Parameters (Support both POST and GET) ---
// =================================================================================

$employee_id = isset($_POST['employee_id']) ? $_POST['employee_id'] : (isset($_GET['employee_id']) ? $_GET['employee_id'] : '');
$employee_name = isset($_POST['employee_name']) ? $_POST['employee_name'] : (isset($_GET['employee_name']) ? $_GET['employee_name'] : '');
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';

if (empty($employee_id)) {
    die("Employee ID is required");
}

// =================================================================================
// --- Build Query with Date Filters ---
// =================================================================================

$sql = "SELECT * FROM attendance WHERE employee_id = ?";
$params = [$employee_id];

if (!empty($from_date)) {
    $sql .= " AND date >= ?";
    $params[] = $from_date;
}

if (!empty($to_date)) {
    $sql .= " AND date <= ?";
    $params[] = $to_date;
}

$sql .= " ORDER BY date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
// --- Get Employee Data ---
// =================================================================================

$employee_id = isset($_POST['employee_id']) ? $_POST['employee_id'] : (isset($_GET['employee_id']) ? $_GET['employee_id'] : '');
$employee_name = isset($_POST['employee_name']) ? $_POST['employee_name'] : (isset($_GET['employee_name']) ? $_GET['employee_name'] : '');

if (empty($employee_id)) {
    die("Employee ID is required");
}

// Get employee details
$employee_sql = "SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC";
$stmt = $pdo->prepare($employee_sql);
$stmt->execute([$employee_id]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($records)) {
    die("No attendance records found for this employee");
}

// Get employee name from first record
if (empty($employee_name) && !empty($records)) {
    $employee_name = $records[0]['employee_name'];
}

// =================================================================================
// --- Create Excel File ---
// =================================================================================

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator("HRMS Paluan")
    ->setLastModifiedBy("HRMS Paluan")
    ->setTitle("Attendance Record - $employee_name")
    ->setSubject("Employee Attendance")
    ->setDescription("Attendance records for $employee_name");

// Set column widths
$sheet->getColumnDimension('A')->setWidth(15);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(15);
$sheet->getColumnDimension('E')->setWidth(15);
$sheet->getColumnDimension('F')->setWidth(15);
$sheet->getColumnDimension('G')->setWidth(15);
$sheet->getColumnDimension('H')->setWidth(15);
$sheet->getColumnDimension('I')->setWidth(15);
$sheet->getColumnDimension('J')->setWidth(15);
$sheet->getColumnDimension('K')->setWidth(15);

// Title
$sheet->setCellValue('A1', 'ATTENDANCE RECORD');
$sheet->mergeCells('A1:K1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Employee Info
$sheet->setCellValue('A3', 'Employee ID:');
$sheet->setCellValue('B3', $employee_id);
$sheet->setCellValue('A4', 'Employee Name:');
$sheet->setCellValue('B4', $employee_name);
$sheet->getStyle('A3:A4')->getFont()->setBold(true);

// Date range
$earliest_date = end($records)['date'];
$latest_date = $records[0]['date'];
$sheet->setCellValue('A5', 'Date Range:');
$sheet->setCellValue('B5', date('M d, Y', strtotime($earliest_date)) . ' to ' . date('M d, Y', strtotime($latest_date)));
$sheet->getStyle('A5')->getFont()->setBold(true);

// Headers
$headers = ['Date', 'Day', 'AM In', 'AM Out', 'PM In', 'PM Out', 'OT Hours', 'Undertime', 'Total Hours'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '7', $header);
    $col++;
}

// Style headers
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1048CB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A7:K7')->applyFromArray($headerStyle);

// Fill data
$row = 8;
$total_hours_sum = 0;
$total_ot_sum = 0;
$total_undertime_sum = 0;

foreach ($records as $record) {
    $date = date('Y-m-d', strtotime($record['date']));
    $day_name = date('D', strtotime($record['date']));

    $sheet->setCellValue('A' . $row, $date);
    $sheet->setCellValue('B' . $row, $day_name);
    $sheet->setCellValue('C' . $row, !empty($record['am_time_in']) ? date('h:i A', strtotime($record['am_time_in'])) : '--');
    $sheet->setCellValue('D' . $row, !empty($record['am_time_out']) ? date('h:i A', strtotime($record['am_time_out'])) : '--');
    $sheet->setCellValue('E' . $row, !empty($record['pm_time_in']) ? date('h:i A', strtotime($record['pm_time_in'])) : '--');
    $sheet->setCellValue('F' . $row, !empty($record['pm_time_out']) ? date('h:i A', strtotime($record['pm_time_out'])) : '--');
    $sheet->setCellValue('G' . $row, $record['ot_hours'] . 'h');
    $sheet->setCellValue('H' . $row, $record['under_time'] . 'h');
    $sheet->setCellValue('I' . $row, $record['total_hours'] . 'h');

    $total_hours_sum += $record['total_hours'];
    $total_ot_sum += $record['ot_hours'];
    $total_undertime_sum += $record['under_time'];

    $row++;
}

// Summary row
$summary_row = $row + 1;
$sheet->setCellValue('A' . $summary_row, 'TOTALS:');
$sheet->mergeCells('A' . $summary_row . ':F' . $summary_row);
$sheet->setCellValue('G' . $summary_row, round($total_ot_sum, 1) . 'h');
$sheet->setCellValue('H' . $summary_row, round($total_undertime_sum, 1) . 'h');
$sheet->setCellValue('I' . $summary_row, round($total_hours_sum, 1) . 'h');
$sheet->getStyle('A' . $summary_row . ':I' . $summary_row)->getFont()->setBold(true);

// Footer
$footer_row = $summary_row + 2;
$sheet->setCellValue('A' . $footer_row, 'Generated on: ' . date('F d, Y h:i A'));
$sheet->mergeCells('A' . $footer_row . ':K' . $footer_row);
$sheet->getStyle('A' . $footer_row)->getFont()->setItalic(true);

// Format borders
$sheet->getStyle('A7:I' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A' . $summary_row . ':I' . $summary_row)->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);

// Set alignment
$sheet->getStyle('A7:I' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A' . $summary_row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// =================================================================================
// --- Output File ---
// =================================================================================

$filename = 'attendance_' . preg_replace('/[^a-zA-Z0-9]/', '_', $employee_name) . '_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
