<?php
// Turn off all error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    ob_clean();
    header('Content-Type: application/json');
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
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'PhpSpreadsheet library not installed. Please run: composer require phpoffice/phpspreadsheet']);
    exit();
}

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'u420482914_paluan_hrms');
define('DB_PASSWORD', 'Hrms_Paluan01');
define('DB_NAME', 'u420482914_hrms_paluan');

// =================================================================================
// --- Database Connection ---
// =================================================================================

try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");
} catch (PDOException $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection error.']);
    exit();
}

// =================================================================================
// --- Get Selected Employee ID from Form ---
// =================================================================================

$selected_employee_id = isset($_POST['selected_employee_id']) ? trim($_POST['selected_employee_id']) : '';
$manual_employee_id = isset($_POST['manual_employee_id']) ? trim($_POST['manual_employee_id']) : '';

// Use manual employee ID if provided
if (!empty($manual_employee_id) && empty($selected_employee_id)) {
    $selected_employee_id = $manual_employee_id;
}

if (empty($selected_employee_id)) {
    $response = [
        'success' => false,
        'imported' => 0,
        'duplicates' => 0,
        'errors' => 0,
        'invalid_employees' => 1,
        'message' => 'Import failed: No employee selected',
        'error_messages' => ['No employee selected. Please select an employee from the list or enter an Employee ID.']
    ];

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// =================================================================================
// --- Helper Functions ---
// =================================================================================

/**
 * Validate if employee exists by ID only (simpler, more reliable)
 */
function validateEmployeeById($pdo, $employee_id)
{
    $employee_id = trim($employee_id);

    if (empty($employee_id)) {
        return ['exists' => false, 'message' => 'Empty employee ID'];
    }

    // Check permanent table
    $sql = "SELECT employee_id, CONCAT(first_name, ' ', last_name) as full_name, office as department, 'Permanent' as employee_type 
            FROM permanent 
            WHERE employee_id = ? AND status = 'Active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$employee_id]);

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        return [
            'exists' => true,
            'employee_id' => $row['employee_id'],
            'employee_name' => $row['full_name'],
            'department' => $row['department'],
            'type' => $row['employee_type']
        ];
    }

    // Check job_order table
    $sql = "SELECT employee_id, employee_name as full_name, office as department, 'Job Order' as employee_type 
            FROM job_order 
            WHERE employee_id = ? AND is_archived = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$employee_id]);

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        return [
            'exists' => true,
            'employee_id' => $row['employee_id'],
            'employee_name' => $row['full_name'],
            'department' => $row['department'],
            'type' => $row['employee_type']
        ];
    }

    // Check contractofservice table
    $sql = "SELECT employee_id, full_name, office as department, 'Contractual' as employee_type 
            FROM contractofservice 
            WHERE employee_id = ? AND status = 'active'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$employee_id]);

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        return [
            'exists' => true,
            'employee_id' => $row['employee_id'],
            'employee_name' => $row['full_name'],
            'department' => $row['department'],
            'type' => $row['employee_type']
        ];
    }

    return [
        'exists' => false,
        'message' => "Employee ID '{$employee_id}' not found in database"
    ];
}

/**
 * Format time value from Excel
 */
function formatTimeValue($timeValue)
{
    if ($timeValue === null || $timeValue === '' || $timeValue == 'NULL' || $timeValue == 'null') {
        return null;
    }

    // Handle numeric Excel time
    if (is_numeric($timeValue)) {
        try {
            $timestamp = Date::excelToTimestamp($timeValue);
            return date('H:i:s', $timestamp);
        } catch (Exception $e) {
            return null;
        }
    }

    $timeStr = trim((string)$timeValue);

    // Format HH:MM:SS
    if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $timeStr, $matches)) {
        $hour = intval($matches[1]);
        $minute = intval($matches[2]);
        $second = intval($matches[3]);
        return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    }

    // Format HH:MM
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeStr, $matches)) {
        $hour = intval($matches[1]);
        $minute = intval($matches[2]);
        return sprintf('%02d:%02d:00', $hour, $minute);
    }

    return null;
}

/**
 * Calculate working hours
 */
function calculateWorkingHours($am_in, $am_out, $pm_in, $pm_out)
{
    $total_minutes = 0;
    $ot_minutes = 0;
    $undertime_minutes = 0;

    $standard_start_am = strtotime('08:00:00');
    $standard_end_pm = strtotime('17:00:00');

    // AM calculation
    if ($am_in && $am_out) {
        $in = is_string($am_in) ? strtotime($am_in) : null;
        $out = is_string($am_out) ? strtotime($am_out) : null;

        if ($in && $out && $out > $in) {
            $worked = ($out - $in) / 60;
            $total_minutes += $worked;

            if ($in < $standard_start_am) {
                $ot_minutes += ($standard_start_am - $in) / 60;
            }

            if ($in > $standard_start_am) {
                $undertime_minutes += ($in - $standard_start_am) / 60;
            }
        }
    }

    // PM calculation
    if ($pm_in && $pm_out) {
        $in = is_string($pm_in) ? strtotime($pm_in) : null;
        $out = is_string($pm_out) ? strtotime($pm_out) : null;

        if ($in && $out && $out > $in) {
            $worked = ($out - $in) / 60;
            $total_minutes += $worked;

            if ($out > $standard_end_pm) {
                $ot_minutes += ($out - $standard_end_pm) / 60;
            }

            if ($out < $standard_end_pm) {
                $undertime_minutes += ($standard_end_pm - $out) / 60;
            }
        }
    }

    return [
        'total_hours' => round($total_minutes / 60, 2),
        'ot_hours' => round($ot_minutes / 60, 2),
        'undertime_hours' => round($undertime_minutes / 60, 2)
    ];
}

/**
 * Extract employee info from worksheet (simplified)
 */
function extractEmployeeInfo($worksheet)
{
    $info = [
        'id' => '',
        'name' => ''
    ];

    // Try to get employee ID from I5 (common location in your DTR)
    try {
        $idCell = $worksheet->getCell('I5')->getValue();
        if (!empty($idCell)) {
            $info['id'] = trim((string)$idCell);
            // Remove non-numeric characters for ID
            $info['id'] = preg_replace('/[^0-9]/', '', $info['id']);
        }
    } catch (Exception $e) {
        // Ignore
    }

    // Try to get name from H5 (common location)
    try {
        $nameCell = $worksheet->getCell('H5')->getValue();
        if (!empty($nameCell)) {
            $info['name'] = trim((string)$nameCell);
        }
    } catch (Exception $e) {
        // Ignore
    }

    // If still empty, try other cells
    if (empty($info['name'])) {
        $possibleNameCells = ['B5', 'C5', 'D5', 'E5'];
        foreach ($possibleNameCells as $cell) {
            try {
                $value = $worksheet->getCell($cell)->getValue();
                if (!empty($value) && is_string($value) && strlen($value) > 3) {
                    $info['name'] = trim($value);
                    break;
                }
            } catch (Exception $e) {
                continue;
            }
        }
    }

    return $info;
}

// =================================================================================
// --- File Upload Validation ---
// =================================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if (!isset($_FILES['xlsx_file'])) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit();
}

$file = $_FILES['xlsx_file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $file['error']]);
    exit();
}

// =================================================================================
// --- Main Processing ---
// =================================================================================

$response = [
    'success' => false,
    'imported' => 0,
    'duplicates' => 0,
    'errors' => 0,
    'invalid_employees' => 0,
    'message' => '',
    'error_messages' => [],
    'records' => [],
    'duplicate_employees' => []
];

try {
    // Load the spreadsheet
    $spreadsheet = IOFactory::load($file['tmp_name']);

    // Use the first sheet
    $worksheet = $spreadsheet->getSheet(0);
    $sheetName = $worksheet->getTitle();

    // Validate employee exists using the selected ID
    $validation = validateEmployeeById($pdo, $selected_employee_id);

    if (!$validation['exists']) {
        $response['invalid_employees'] = 1;
        $response['error_messages'][] = $validation['message'];
        $response['message'] = "Import failed: Employee ID '{$selected_employee_id}' not found";

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Extract date information from the DTR
    $year = date('Y');
    $month = date('m');

    // Try to get month/year from the file
    try {
        $dateCell = $worksheet->getCell('D2')->getValue();
        if (is_string($dateCell) && preg_match('/(\d{4})-(\d{2})-\d{2}/', $dateCell, $matches)) {
            $year = intval($matches[1]);
            $month = intval($matches[2]);
        }
    } catch (Exception $e) {
        // Use current date if extraction fails
    }

    $pdo->beginTransaction();

    // Process attendance rows
    $imported = 0;
    $duplicates = 0;
    $records = [];
    $duplicateRecords = [];

    // Find where data starts - look for day numbers
    $startRow = 0;
    for ($row = 10; $row <= 20; $row++) {
        try {
            $cellValue = $worksheet->getCell('A' . $row)->getValue();
            if (!empty($cellValue) && preg_match('/^\d{1,2}\s+[A-Za-z]{2}/', trim((string)$cellValue))) {
                $startRow = $row;
                break;
            }
        } catch (Exception $e) {
            continue;
        }
    }

    if ($startRow == 0) {
        $startRow = 12; // Default fallback
    }

    // Process rows
    for ($row = $startRow; $row <= $startRow + 35; $row++) {
        try {
            $dayCell = $worksheet->getCell('A' . $row)->getValue();

            if (empty($dayCell)) {
                continue;
            }

            $dayStr = trim((string)$dayCell);

            if (!preg_match('/^(\d{1,2})/', $dayStr, $matches)) {
                continue;
            }

            $day = intval($matches[1]);

            if ($day < 1 || $day > 31) {
                continue;
            }

            // Get time values
            $amIn = $worksheet->getCell('B' . $row)->getValue();
            $amOut = $worksheet->getCell('D' . $row)->getValue();
            $pmIn = $worksheet->getCell('G' . $row)->getValue();
            $pmOut = $worksheet->getCell('I' . $row)->getValue();

            // Check if has data
            $hasData = false;
            if (!empty($amIn) && $amIn != 'null' && $amIn != 'NULL') $hasData = true;
            if (!empty($amOut) && $amOut != 'null' && $amOut != 'NULL') $hasData = true;
            if (!empty($pmIn) && $pmIn != 'null' && $pmIn != 'NULL') $hasData = true;
            if (!empty($pmOut) && $pmOut != 'null' && $pmOut != 'NULL') $hasData = true;

            if (!$hasData) {
                continue;
            }

            $dateStr = sprintf('%d-%02d-%02d', $year, $month, $day);

            // Check duplicate
            $checkSql = "SELECT id FROM attendance WHERE employee_id = ? AND date = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$validation['employee_id'], $dateStr]);

            if ($checkStmt->rowCount() > 0) {
                $duplicates++;
                $duplicateRecords[] = [
                    'employee_id' => $validation['employee_id'],
                    'employee_name' => $validation['employee_name'],
                    'date' => $dateStr,
                    'day' => $day
                ];
                continue;
            }

            // Format times
            $amInFormatted = formatTimeValue($amIn);
            $amOutFormatted = formatTimeValue($amOut);
            $pmInFormatted = formatTimeValue($pmIn);
            $pmOutFormatted = formatTimeValue($pmOut);

            // Calculate hours
            $hours = calculateWorkingHours($amInFormatted, $amOutFormatted, $pmInFormatted, $pmOutFormatted);

            // Insert record
            $sql = "INSERT INTO attendance 
                    (date, employee_id, employee_name, department, am_time_in, am_time_out, pm_time_in, pm_time_out, ot_hours, under_time, total_hours) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);

            if ($stmt->execute([
                $dateStr,
                $validation['employee_id'],
                $validation['employee_name'],
                $validation['department'],
                $amInFormatted,
                $amOutFormatted,
                $pmInFormatted,
                $pmOutFormatted,
                $hours['ot_hours'],
                $hours['undertime_hours'],
                $hours['total_hours']
            ])) {
                $imported++;
                $records[] = [
                    'employee' => $validation['employee_name'],
                    'employee_id' => $validation['employee_id'],
                    'date' => $dateStr,
                    'day' => $day,
                    'am_in' => $amInFormatted,
                    'am_out' => $amOutFormatted,
                    'pm_in' => $pmInFormatted,
                    'pm_out' => $pmOutFormatted,
                    'total_hours' => $hours['total_hours']
                ];
            }
        } catch (Exception $e) {
            $response['error_messages'][] = "Error processing row {$row}: " . $e->getMessage();
            continue;
        }
    }

    $pdo->commit();

    $response['success'] = true;
    $response['imported'] = $imported;
    $response['duplicates'] = $duplicates;
    $response['records'] = $records;
    $response['duplicate_employees'] = $duplicateRecords;

    $response['message'] = "Successfully imported $imported attendance records for {$validation['employee_name']}!";

    if ($duplicates > 0) {
        $response['message'] .= " Skipped $duplicates duplicate records.";
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['success'] = false;
    $response['message'] = 'Error processing file: ' . $e->getMessage();
    $response['error_messages'][] = $e->getMessage();
}

// Clear output buffer and send JSON
ob_clean();
header('Content-Type: application/json');
echo json_encode($response);
exit();
