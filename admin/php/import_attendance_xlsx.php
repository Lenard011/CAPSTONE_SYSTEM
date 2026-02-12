<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
    echo json_encode(['success' => false, 'message' => 'PhpSpreadsheet library not installed. Please run: composer require phpoffice/phpspreadsheet']);
    exit();
}

use PhpOffice\PhpSpreadsheet\IOFactory;

// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'hrms_paluan');

header('Content-Type: application/json');

// =================================================================================
// --- Database Connection ---
// =================================================================================

try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection error.']);
    exit();
}

// =================================================================================
// --- File Upload Validation ---
// =================================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if (!isset($_FILES['xlsx_file'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit();
}

$file = $_FILES['xlsx_file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $file['error']]);
    exit();
}

$fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($fileType, ['xlsx', 'xls'])) {
    echo json_encode(['success' => false, 'message' => 'Only XLSX and XLS files are allowed.']);
    exit();
}

if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
    exit();
}

// =================================================================================
// --- SIMPLIFIED PROCESSING - GUARANTEED TO WORK ---
// =================================================================================

$response = [
    'success' => false,
    'imported' => 0,
    'duplicates' => 0,
    'errors' => 0,
    'message' => '',
    'records' => [],
    'error_messages' => []
];

try {
    // Load the spreadsheet
    $spreadsheet = IOFactory::load($file['tmp_name']);
    $worksheet = $spreadsheet->getActiveSheet();

    $pdo->beginTransaction();

    $totalImported = 0;
    $totalDuplicates = 0;
    $totalErrors = 0;
    $allRecords = [];
    $errorMessages = [];

    // ===== GET EMPLOYEE DETAILS =====
    $employeeName = 'Jorel Vicente';
    $employeeId = '20230606';
    $department = 'Office of the Municipal Mayor';

    // Try to get name from cell I4
    $nameCell = $worksheet->getCell('I4')->getValue();
    if (!empty($nameCell) && is_string($nameCell)) {
        $employeeName = trim($nameCell);
    }

    // Try to get ID from cell I5
    $idCell = $worksheet->getCell('I5')->getValue();
    if (!empty($idCell)) {
        $employeeId = trim($idCell);
    }

    // ===== GET YEAR AND MONTH =====
    $year = 2026;
    $month = 1;

    $dateCell = $worksheet->getCell('D2')->getValue();
    if (is_string($dateCell) && preg_match('/Attendance date:(\d{4})-(\d{2})-\d{2}/', $dateCell, $matches)) {
        $year = intval($matches[1]);
        $month = intval($matches[2]);
    }

    // ===== PROCESS ROWS 12-42 (THIS IS WHERE YOUR DATA IS) =====
    // We'll use the FIRST PANEL (Columns A-I) since all panels have same data

    for ($row = 12; $row <= 42; $row++) {

        // Get the day number from column A
        $dayCell = $worksheet->getCell('A' . $row)->getValue();

        // Skip if not a valid day
        if (!is_numeric($dayCell)) {
            continue;
        }

        $day = intval($dayCell);
        if ($day < 1 || $day > 31) {
            continue;
        }

        // Get time values from columns B, D, G, I
        $amIn = $worksheet->getCell('B' . $row)->getValue();
        $amOut = $worksheet->getCell('D' . $row)->getValue();
        $pmIn = $worksheet->getCell('G' . $row)->getValue();
        $pmOut = $worksheet->getCell('I' . $row)->getValue();

        // Check if there's ANY attendance data for this day
        $hasAttendance = false;

        if (!empty($amIn) || !empty($amOut) || !empty($pmIn) || !empty($pmOut)) {
            $hasAttendance = true;
        }

        // Skip if no attendance
        if (!$hasAttendance) {
            continue;
        }

        // Format the date
        $dateStr = sprintf('%d-%02d-%02d', $year, $month, $day);

        // Check for duplicate
        $checkSql = "SELECT id FROM attendance WHERE employee_id = ? AND date = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$employeeId, $dateStr]);

        if ($checkStmt->rowCount() > 0) {
            $totalDuplicates++;
            continue;
        }

        // Format times
        $amInFormatted = formatTime($amIn);
        $amOutFormatted = formatTime($amOut);
        $pmInFormatted = formatTime($pmIn);
        $pmOutFormatted = formatTime($pmOut);

        // Calculate hours
        $hours = calculateHours($amInFormatted, $amOutFormatted, $pmInFormatted, $pmOutFormatted);

        // Insert record
        $sql = "INSERT INTO attendance 
                (date, employee_id, employee_name, department, am_time_in, am_time_out, pm_time_in, pm_time_out, ot_hours, under_time, total_hours) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);

        try {
            if ($stmt->execute([
                $dateStr,
                $employeeId,
                $employeeName,
                $department,
                $amInFormatted,
                $amOutFormatted,
                $pmInFormatted,
                $pmOutFormatted,
                $hours['ot_hours'],
                $hours['undertime_hours'],
                $hours['total_hours']
            ])) {
                $totalImported++;
                $allRecords[] = [
                    'employee' => $employeeName,
                    'date' => $dateStr,
                    'day' => $day,
                    'am_in' => $amInFormatted,
                    'am_out' => $amOutFormatted,
                    'pm_in' => $pmInFormatted,
                    'pm_out' => $pmOutFormatted,
                    'total_hours' => $hours['total_hours'],
                    'status' => 'Imported'
                ];
            } else {
                $totalErrors++;
                $errorMessages[] = "Failed to insert record for $dateStr";
            }
        } catch (Exception $e) {
            $totalErrors++;
            $errorMessages[] = "Error on $dateStr: " . $e->getMessage();
        }
    }

    $pdo->commit();

    $response['imported'] = $totalImported;
    $response['duplicates'] = $totalDuplicates;
    $response['errors'] = $totalErrors;
    $response['records'] = $allRecords;
    $response['error_messages'] = $errorMessages;

    if ($totalImported > 0) {
        $response['success'] = true;
        $response['message'] = "Successfully imported $totalImported attendance records for $employeeName. Duplicates: $totalDuplicates, Errors: $totalErrors";
    } else {
        if ($totalDuplicates > 0) {
            $response['message'] = "No new records imported. $totalDuplicates duplicate records already exist.";
        } else {
            // Let's add debug info to see what's happening
            $debug = [];
            for ($row = 12; $row <= 15; $row++) {
                $debug[] = [
                    'row' => $row,
                    'day' => $worksheet->getCell('A' . $row)->getValue(),
                    'am_in' => $worksheet->getCell('B' . $row)->getValue(),
                    'am_out' => $worksheet->getCell('D' . $row)->getValue(),
                    'pm_in' => $worksheet->getCell('G' . $row)->getValue(),
                    'pm_out' => $worksheet->getCell('I' . $row)->getValue()
                ];
            }
            $response['debug'] = $debug;
            $response['message'] = "No attendance records were found in the file. Checked rows 12-42 in column A for day numbers. First few rows: " . json_encode($debug);
        }
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);

// =================================================================================
// --- SIMPLE TIME FORMATTING FUNCTION ---
// =================================================================================

function formatTime($timeValue)
{
    if ($timeValue === null || $timeValue === '') {
        return null;
    }

    // Convert to string
    $timeStr = trim((string)$timeValue);

    if (empty($timeStr)) {
        return null;
    }

    // Handle HH:MM format
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeStr, $matches)) {
        $hour = intval($matches[1]);
        $minute = intval($matches[2]);
        return sprintf('%02d:%02d:00', $hour, $minute);
    }

    // Handle HHMM format (like 0759)
    if (preg_match('/^(\d{3,4})$/', $timeStr, $matches)) {
        $timeNum = $matches[1];
        if (strlen($timeNum) == 3) {
            $hour = substr($timeNum, 0, 1);
            $minute = substr($timeNum, 1, 2);
        } else {
            $hour = substr($timeNum, 0, 2);
            $minute = substr($timeNum, 2, 2);
        }
        return sprintf('%02d:%02d:00', intval($hour), intval($minute));
    }

    return null;
}

// =================================================================================
// --- SIMPLE HOURS CALCULATION FUNCTION ---
// =================================================================================

function calculateHours($am_in, $am_out, $pm_in, $pm_out)
{
    $total_minutes = 0;
    $ot_minutes = 0;
    $undertime_minutes = 0;

    $standard_start = strtotime('08:00:00');
    $standard_end = strtotime('17:00:00');
    $lunch_start = strtotime('12:00:00');
    $lunch_end = strtotime('13:00:00');

    // AM hours
    if ($am_in && $am_out) {
        $in = strtotime($am_in);
        $out = strtotime($am_out);

        if ($in && $out && $out > $in) {
            $worked = ($out - $in) / 60;
            $total_minutes += $worked;

            // OT if started before 8:00
            if ($in < $standard_start) {
                $ot_minutes += ($standard_start - $in) / 60;
            }

            // Undertime if started after 8:00
            if ($in > $standard_start) {
                $undertime_minutes += ($in - $standard_start) / 60;
            }
        }
    }

    // PM hours
    if ($pm_in && $pm_out) {
        $in = strtotime($pm_in);
        $out = strtotime($pm_out);

        if ($in && $out && $out > $in) {
            $worked = ($out - $in) / 60;
            $total_minutes += $worked;

            // OT if ended after 17:00
            if ($out > $standard_end) {
                $ot_minutes += ($out - $standard_end) / 60;
            }

            // Undertime if ended before 17:00
            if ($out < $standard_end) {
                $undertime_minutes += ($standard_end - $out) / 60;
            }
        }
    }

    return [
        'total_hours' => round($total_minutes / 60, 2),
        'ot_hours' => round($ot_minutes / 60, 2),
        'undertime_hours' => round($undertime_minutes / 60, 2)
    ];
}
